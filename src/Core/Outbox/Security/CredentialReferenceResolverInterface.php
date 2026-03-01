<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Security;

interface CredentialReferenceResolverInterface
{
    public function supports(string $reference): bool;

    public function resolve(string $reference): string;
}
