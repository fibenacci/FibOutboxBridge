<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Security;

class OutboxCredentialResolver implements OutboxCredentialResolverInterface
{
    /**
     * @var list<CredentialReferenceResolverInterface>
     */
    private array $referenceResolvers = [];

    /**
     * @param iterable<CredentialReferenceResolverInterface> $referenceResolvers
     */
    public function __construct(iterable $referenceResolvers)
    {
        foreach ($referenceResolvers as $referenceResolver) {
            $this->referenceResolvers[] = $referenceResolver;
        }
    }

    public function resolveConfig(array $config): array
    {
        $resolved = [];

        foreach ($config as $key => $value) {
            $key = (string) $key;

            if (!empty($value) && str_ends_with($key, 'Ref')) {
                $targetKey = substr($key, 0, -3);
                $referenceValue = (string) $value;
                if ($targetKey === '') {
                    continue;
                }

                $resolved[$targetKey] = $this->resolveReference($referenceValue);

                continue;
            }

            if (!empty($value) && $this->isReference((string) $value)) {
                $resolved[$key] = $this->resolveReference((string) $value);

                continue;
            }

            if ($value === (array) $value) {
                $resolved[$key] = $this->resolveConfig($value);

                continue;
            }

            $resolved[$key] = $value;
        }

        return $resolved;
    }

    private function isReference(string $value): bool
    {
        foreach ($this->referenceResolvers as $resolver) {
            if ($resolver->supports($value)) {
                return true;
            }
        }

        return false;
    }

    private function resolveReference(string $reference): string
    {
        foreach ($this->referenceResolvers as $resolver) {
            if ($resolver->supports($reference)) {
                return $resolver->resolve($reference);
            }
        }

        throw new \RuntimeException(sprintf('Unsupported credential reference "%s".', $reference));
    }
}
