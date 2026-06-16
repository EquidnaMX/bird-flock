<?php

/**
 * Boot-time configuration validator.
 *
 * PHP 8.1+
 *
 * @package   Equidna\BirdFlock\Support
 * @author    Gabriel Ruelas <gruelas@gruelas.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace Equidna\BirdFlock\Support;

use Equidna\BirdFlock\Contracts\MessageSenderInterface;
use Equidna\BirdFlock\Contracts\SenderConfigValidatorInterface;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Validates runtime configuration for Bird Flock.
 *
 * Checks credentials and emits warnings/errors via the package logger.
 */
final class ConfigValidator
{
    /**
     * Validate all configured settings.
     *
     * Throws on invalid sender structure; missing credentials are emitted as
     * warnings via the package logger.
     *
     * @return void
     * @throws \InvalidArgumentException When sender configuration is invalid
     */
    public function validateAll(): void
    {
        $this->validateCore();
        $this->validateConfiguredSenders();
    }

    /**
     * Validate core configuration (table prefix, default queue).
     *
     * @return void
     */
    private function validateCore(): void
    {
        $prefix = config('bird-flock.tables.prefix');
        if (! is_string($prefix) || trim((string) $prefix) === '') {
            Logger::warning('bird-flock.config.table_prefix_missing', [
                'hint' => 'Set BIRD_FLOCK_TABLE_PREFIX to avoid unexpected table names',
            ]);
        }

        $queue = config('bird-flock.default_queue');
        if (! is_string($queue) || trim((string) $queue) === '') {
            Logger::info('bird-flock.config.default_queue_missing', [
                'hint' => 'Using default queue. Set BIRD_FLOCK_DEFAULT_QUEUE to customize',
            ]);
        }
    }

    private function validateConfiguredSenders(): void
    {
        $channels = config('bird-flock.channels', []);

        if (! is_array($channels) || $channels === []) {
            Logger::warning('bird-flock.config.channels_missing', [
                'hint' => 'Configure at least one channel in bird-flock.channels',
            ]);
            return;
        }

        $resolver = new SenderResolver();
        $vendorSelector = new VendorSelector();

        foreach ($channels as $channel => $channelConfig) {
            if (! is_string($channel) || trim($channel) === '' || ! is_array($channelConfig)) {
                throw new InvalidArgumentException('Bird Flock channel configuration keys must be non-empty strings');
            }

            $senderDefinitions = [];

            if (array_key_exists('senders', $channelConfig)) {
                if (! is_array($channelConfig['senders']) || $channelConfig['senders'] === []) {
                    throw new InvalidArgumentException(
                        "Channel '{$channel}' must define at least one sender"
                    );
                }

                $senderDefinitions = $channelConfig['senders'];
            } elseif (array_key_exists('vendors', $channelConfig)) {
                $vendors = $vendorSelector->normalizeVendors($channelConfig['vendors']);

                if ($vendors === []) {
                    throw new InvalidArgumentException(
                        "Channel '{$channel}' must define at least one legacy vendor"
                    );
                }

                Logger::info('bird-flock.config.legacy_vendors_used', [
                    'channel' => $channel,
                    'hint' => 'Replace vendors with senders keyed by vendor for config-driven sender resolution',
                ]);

                foreach ($vendors as $vendor) {
                    $senderDefinitions[$vendor] = $resolver->senderDefinition($channel, $vendor);
                }
            } else {
                throw new InvalidArgumentException(
                    "Channel '{$channel}' must define senders"
                );
            }

            foreach ($senderDefinitions as $vendor => $definition) {
                if (! is_string($vendor) || trim($vendor) === '') {
                    throw new InvalidArgumentException(
                        "Channel '{$channel}' sender keys must be non-empty vendor names"
                    );
                }

                $normalized = $resolver->normalizeSenderDefinition($channel, $vendor, $definition);
                $this->validateSenderClass($channel, $vendor, $normalized);
                $this->validateRequiredArguments($channel, $vendor, $normalized);
                $this->runSenderValidator($channel, $vendor, $normalized);
            }
        }
    }

    /**
     * @param array{sender: class-string, arguments?: array<string, mixed>, validator?: class-string} $definition
     */
    private function validateSenderClass(string $channel, string $vendor, array $definition): void
    {
        if (! is_subclass_of($definition['sender'], MessageSenderInterface::class)) {
            throw new InvalidArgumentException(
                "Sender '{$vendor}' for channel '{$channel}' must implement " . MessageSenderInterface::class
            );
        }
    }

    /**
     * @param array{sender: class-string, arguments?: array<string, mixed>, validator?: class-string} $definition
     */
    private function validateRequiredArguments(string $channel, string $vendor, array $definition): void
    {
        $constructor = (new ReflectionClass($definition['sender']))->getConstructor();

        if ($constructor === null) {
            return;
        }

        $arguments = $definition['arguments'] ?? [];

        foreach ($constructor->getParameters() as $parameter) {
            if (array_key_exists($parameter->getName(), $arguments)) {
                $this->warnWhenRequiredConfigReferenceIsNull(
                    $channel,
                    $vendor,
                    $parameter->getName(),
                    $arguments[$parameter->getName()],
                    $parameter->allowsNull()
                );
                continue;
            }

            $type = $parameter->getType();
            $isClassDependency = $type instanceof ReflectionNamedType && ! $type->isBuiltin();

            if ($isClassDependency || $parameter->isDefaultValueAvailable() || $parameter->allowsNull()) {
                continue;
            }

            throw new InvalidArgumentException(
                "Sender '{$vendor}' for channel '{$channel}' is missing required constructor argument " .
                    "'{$parameter->getName()}'"
            );
        }
    }

    private function warnWhenRequiredConfigReferenceIsNull(
        string $channel,
        string $vendor,
        string $argument,
        mixed $value,
        bool $allowsNull
    ): void {
        if ($allowsNull || ! is_string($value) || ! str_starts_with($value, 'config:')) {
            return;
        }

        $configKey = substr($value, strlen('config:'));

        if (config($configKey) !== null) {
            return;
        }

        Logger::warning('bird-flock.config.sender_argument_null', [
            'channel' => $channel,
            'vendor' => $vendor,
            'argument' => $argument,
            'config_key' => $configKey,
            'hint' => 'Set the referenced config value before sending through this sender',
        ]);
    }

    /**
     * @param array{sender: class-string, arguments?: array<string, mixed>, validator?: class-string} $definition
     */
    private function runSenderValidator(string $channel, string $vendor, array $definition): void
    {
        if (! isset($definition['validator'])) {
            return;
        }

        if (! is_subclass_of($definition['validator'], SenderConfigValidatorInterface::class)) {
            throw new InvalidArgumentException(
                "Validator for '{$channel}:{$vendor}' must implement " . SenderConfigValidatorInterface::class
            );
        }

        /** @var SenderConfigValidatorInterface $validator */
        $validator = app()->make($definition['validator']);
        $validator->validate($channel, $vendor, $definition);
    }
}
