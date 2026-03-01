<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Security\Resolver;

use Fib\OutboxBridge\Core\Outbox\Security\CredentialReferenceResolverInterface;

class EnvCredentialReferenceResolver implements CredentialReferenceResolverInterface
{
    public function supports(string $reference): bool
    {
        return str_starts_with($reference, 'env:');
    }

    public function resolve(string $reference): string
    {
        $envName = substr($reference, 4);
        if ($envName === '') {
            throw new \RuntimeException('Credential env reference must not be empty.');
        }

        $value = getenv($envName);
        if (empty($value)) {
            throw new \RuntimeException(sprintf('Credential env reference "%s" is missing or empty.', $envName));
        }

        return (string) $value;
    }
}
