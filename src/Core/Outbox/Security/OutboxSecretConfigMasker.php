<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Security;

class OutboxSecretConfigMasker
{
    public const MASK_PLACEHOLDER = '********';

    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function maskConfig(array $config): array
    {
        $masked = [];

        foreach ($config as $key => $value) {
            $key = (string) $key;

            if ($this->isSecretKey($key)) {
                if (!empty($value)) {
                    $masked[$key] = self::MASK_PLACEHOLDER;

                    continue;
                }
            }

            if ($value === (array) $value) {
                $masked[$key] = $this->maskConfig($value);

                continue;
            }

            $masked[$key] = $value;
        }

        return $masked;
    }

    /**
     * @param array<string, mixed> $newConfig
     * @param array<string, mixed> $existingConfig
     *
     * @return array<string, mixed>
     */
    public function restoreMaskedPlaceholders(array $newConfig, array $existingConfig): array
    {
        $merged = [];

        foreach ($newConfig as $key => $value) {
            $key = (string) $key;

            $existingValue = $existingConfig[$key] ?? null;

            if ($value === (array) $value) {
                $merged[$key] = $this->restoreMaskedPlaceholders(
                    $value,
                    $existingValue === (array) $existingValue ? $existingValue : []
                );

                continue;
            }

            if ($value === self::MASK_PLACEHOLDER && $this->isSecretKey($key)) {
                if ($existingValue !== null) {
                    $merged[$key] = $existingValue;
                }

                continue;
            }

            $merged[$key] = $value;
        }

        return $merged;
    }

    private function isSecretKey(string $key): bool
    {
        if (preg_match('/ref$/i', $key) === 1) {
            return false;
        }

        return preg_match('/password|passphrase|secret|token|apikey|api_key|privatekey|private_key|accesskey|access_key/i', $key) === 1;
    }
}
