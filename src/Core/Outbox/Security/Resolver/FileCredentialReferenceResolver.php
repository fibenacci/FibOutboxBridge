<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Security\Resolver;

use Fib\OutboxBridge\Core\Outbox\Security\CredentialReferenceResolverInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Local\LocalFilesystemAdapter;

class FileCredentialReferenceResolver implements CredentialReferenceResolverInterface
{
    private readonly Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem(new LocalFilesystemAdapter('/'));
    }

    public function supports(string $reference): bool
    {
        return str_starts_with($reference, 'file:');
    }

    public function resolve(string $reference): string
    {
        $rawPath = substr($reference, 5);

        if ($rawPath === '') {
            throw new \RuntimeException('Credential file reference must not be empty.');
        }

        $path = ltrim($rawPath, '/');

        try {
            $value = $this->filesystem->read($path);
        } catch (FilesystemException $e) {
            throw new \RuntimeException(sprintf('Credential file reference "%s" could not be read.', $rawPath), 0, $e);
        }

        if ($value === '') {
            throw new \RuntimeException(sprintf('Credential file reference "%s" is empty.', $rawPath));
        }

        return $value;
    }
}
