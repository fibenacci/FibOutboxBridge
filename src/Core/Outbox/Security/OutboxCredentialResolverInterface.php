<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Security;

interface OutboxCredentialResolverInterface
{
    /**
     * @param array<string, mixed> $config
     *
     * @return array<string, mixed>
     */
    public function resolveConfig(array $config): array;
}

