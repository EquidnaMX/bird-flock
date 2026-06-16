<?php

namespace Equidna\BirdFlock\Support;

use Equidna\BirdFlock\Contracts\MessageSenderInterface;
use Equidna\BirdFlock\Contracts\SenderDefinitionInterface;
use Equidna\BirdFlock\Senders\Labsmobile\LabsmobileSmsSenderDefinition;
use Equidna\BirdFlock\Senders\Mailgun\MailgunEmailSenderDefinition;
use Equidna\BirdFlock\Senders\Sendgrid\SendgridEmailSenderDefinition;
use Equidna\BirdFlock\Senders\Twilio\TwilioSmsSenderDefinition;
use Equidna\BirdFlock\Senders\Twilio\TwilioWhatsappSenderDefinition;
use Equidna\BirdFlock\Senders\Vonage\VonageSmsSenderDefinition;
use Illuminate\Contracts\Container\BindingResolutionException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SenderResolver
{
    public function __construct(
        private readonly ?VendorSelector $selector = null,
    ) {
        //
    }

    public function resolve(string $channel): MessageSenderInterface
    {
        $vendor = ($this->selector ?? new VendorSelector())->select($channel);

        return $this->make($channel, $vendor);
    }

    public function make(string $channel, string $vendor): MessageSenderInterface
    {
        $definition = $this->senderDefinition($channel, $vendor);
        $senderClass = $definition['sender'];
        $arguments = $this->resolveArguments($definition['arguments'] ?? []);

        try {
            $sender = app()->makeWith($senderClass, $arguments);
        } catch (BindingResolutionException $e) {
            throw new RuntimeException(
                "Unable to resolve sender '{$vendor}' for channel '{$channel}' using class '{$senderClass}': " .
                    $e->getMessage(),
                previous: $e
            );
        } catch (Throwable $e) {
            throw new RuntimeException(
                "Unable to build sender '{$vendor}' for channel '{$channel}' using class '{$senderClass}': " .
                    $e->getMessage(),
                previous: $e
            );
        }

        if (! $sender instanceof MessageSenderInterface) {
            throw new RuntimeException(
                "Sender '{$vendor}' for channel '{$channel}' must implement " . MessageSenderInterface::class
            );
        }

        return $sender;
    }

    /**
     * @return array{sender: class-string, arguments?: array<string, mixed>, validator?: class-string|null}
     */
    public function senderDefinition(string $channel, string $vendor): array
    {
        $channelConfig = config("bird-flock.channels.{$channel}", []);
        $senderConfig = $channelConfig['senders'][$vendor] ?? null;

        if ($senderConfig === null) {
            $senderConfig = $this->builtinSenderDefinitions()[$channel][$vendor] ?? null;
        }

        if ($senderConfig === null) {
            throw new InvalidArgumentException(
                "Sender '{$vendor}' is not configured for channel '{$channel}'"
            );
        }

        return $this->normalizeSenderDefinition($channel, $vendor, $senderConfig);
    }

    /**
     * Built-in definitions keep old published configs that only contain `vendors` working.
     *
     * @return array<string, array<string, class-string<SenderDefinitionInterface>>>
     */
    public function builtinSenderDefinitions(): array
    {
        return [
            'sms' => [
                'twilio' => TwilioSmsSenderDefinition::class,
                'vonage' => VonageSmsSenderDefinition::class,
                'labsmobile' => LabsmobileSmsSenderDefinition::class,
            ],
            'whatsapp' => [
                'twilio' => TwilioWhatsappSenderDefinition::class,
            ],
            'email' => [
                'sendgrid' => SendgridEmailSenderDefinition::class,
                'mailgun' => MailgunEmailSenderDefinition::class,
            ],
        ];
    }

    /**
     * @param mixed $definition
     * @return array{sender: class-string, arguments?: array<string, mixed>, validator?: class-string|null}
     */
    public function normalizeSenderDefinition(string $channel, string $vendor, mixed $definition): array
    {
        if (is_string($definition)) {
            return $this->normalizeClassDefinition($channel, $vendor, $definition);
        }

        if (! is_array($definition)) {
            throw new InvalidArgumentException(
                "Sender '{$vendor}' for channel '{$channel}' must be a class string or config array"
            );
        }

        return $this->normalizeArrayDefinition($channel, $vendor, $definition);
    }

    /**
     * @return array{sender: class-string, arguments?: array<string, mixed>, validator?: class-string|null}
     */
    private function normalizeClassDefinition(string $channel, string $vendor, string $definition): array
    {
        if (! class_exists($definition)) {
            throw new InvalidArgumentException(
                "Sender or definition class '{$definition}' for '{$channel}:{$vendor}' does not exist"
            );
        }

        if (is_subclass_of($definition, SenderDefinitionInterface::class)) {
            /** @var SenderDefinitionInterface $senderDefinition */
            $senderDefinition = app()->make($definition);

            return $this->normalizeDefinitionObject($channel, $vendor, $senderDefinition);
        }

        if (is_subclass_of($definition, MessageSenderInterface::class)) {
            return [
                'sender' => $definition,
                'arguments' => [],
            ];
        }

        throw new InvalidArgumentException(
            "Sender '{$vendor}' for channel '{$channel}' class '{$definition}' must implement " .
                SenderDefinitionInterface::class . ' or ' . MessageSenderInterface::class
        );
    }

    /**
     * @return array{sender: class-string, arguments?: array<string, mixed>, validator?: class-string|null}
     */
    private function normalizeDefinitionObject(
        string $channel,
        string $vendor,
        SenderDefinitionInterface $definition
    ): array {
        return $this->normalizeArrayDefinition($channel, $vendor, [
            'sender' => $definition->sender(),
            'arguments' => $definition->arguments(),
            'validator' => $definition->validator(),
        ]);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array{sender: class-string, arguments?: array<string, mixed>, validator?: class-string|null}
     */
    private function normalizeArrayDefinition(string $channel, string $vendor, array $definition): array
    {
        $senderClass = $definition['sender'] ?? null;

        if (! is_string($senderClass) || trim($senderClass) === '') {
            throw new InvalidArgumentException(
                "Sender '{$vendor}' for channel '{$channel}' must define a non-empty sender class"
            );
        }

        if (! class_exists($senderClass)) {
            throw new InvalidArgumentException(
                "Sender class '{$senderClass}' for '{$channel}:{$vendor}' does not exist"
            );
        }

        $arguments = $definition['arguments'] ?? [];
        if (! is_array($arguments)) {
            throw new InvalidArgumentException(
                "Sender '{$vendor}' for channel '{$channel}' must define arguments as an array"
            );
        }

        $normalized = [
            'sender' => $senderClass,
            'arguments' => $arguments,
        ];

        if (array_key_exists('validator', $definition) && $definition['validator'] !== null) {
            if (! is_string($definition['validator']) || trim($definition['validator']) === '') {
                throw new InvalidArgumentException(
                    "Sender '{$vendor}' for channel '{$channel}' validator must be a class string"
                );
            }

            if (! class_exists($definition['validator'])) {
                throw new InvalidArgumentException(
                    "Validator class '{$definition['validator']}' for '{$channel}:{$vendor}' does not exist"
                );
            }

            $normalized['validator'] = $definition['validator'];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function resolveArguments(array $arguments): array
    {
        $resolved = [];

        foreach ($arguments as $name => $value) {
            if (! is_string($name) || trim($name) === '') {
                throw new InvalidArgumentException('Sender argument names must be non-empty strings');
            }

            $resolved[$name] = $this->resolveArgumentValue($value);
        }

        return $resolved;
    }

    private function resolveArgumentValue(mixed $value): mixed
    {
        if (is_string($value) && str_starts_with($value, 'config:')) {
            return config(substr($value, strlen('config:')));
        }

        return $value;
    }
}
