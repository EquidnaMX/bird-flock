<?php

namespace Equidna\BirdFlock\Support;

use InvalidArgumentException;

final class VendorSelector
{
    /**
     * @return non-empty-string
     */
    public function select(string $channel): string
    {
        $config = config("bird-flock.channels.{$channel}", []);
        $vendors = $this->configuredVendors($config);

        if ($vendors === []) {
            throw new InvalidArgumentException("No vendors configured for channel: {$channel}");
        }

        $strategy = (string) ($config['strategy'] ?? 'round_robin');

        return match ($strategy) {
            'round_robin' => $this->roundRobin($channel, $vendors),
            'random' => $vendors[random_int(0, count($vendors) - 1)],
            default => throw new InvalidArgumentException(
                "Unsupported vendor selection strategy '{$strategy}' for channel: {$channel}"
            ),
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return list<non-empty-string>
     */
    private function configuredVendors(array $config): array
    {
        if (isset($config['senders']) && is_array($config['senders'])) {
            return $this->normalizeVendors(array_keys($config['senders']));
        }

        return $this->normalizeVendors($config['vendors'] ?? []);
    }

    /**
     * @param array<mixed> $vendors
     * @return list<non-empty-string>
     */
    public function normalizeVendors(array $vendors): array
    {
        $normalized = [];

        foreach ($vendors as $vendor) {
            if (! is_string($vendor)) {
                continue;
            }

            $vendor = strtolower(trim($vendor));

            if ($vendor !== '') {
                $normalized[] = $vendor;
            }
        }

        return array_values($normalized);
    }

    /**
     * @param list<non-empty-string> $vendors
     * @return non-empty-string
     */
    private function roundRobin(string $channel, array $vendors): string
    {
        $key = sprintf(
            'bird-flock:vendor-selector:%s:%s',
            $channel,
            md5(json_encode($vendors) ?: '')
        );

        $position = app('cache')->increment($key);

        return $vendors[((int) $position - 1) % count($vendors)];
    }
}
