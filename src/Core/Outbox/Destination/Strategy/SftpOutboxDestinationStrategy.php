<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination\Strategy;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyInterface;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\PhpseclibV3\SftpAdapter;
use League\Flysystem\PhpseclibV3\SftpConnectionProvider;

class SftpOutboxDestinationStrategy implements OutboxDestinationStrategyInterface
{
    public function getType(): string
    {
        return 'sftp';
    }

    public function getLabel(): string
    {
        return 'SFTP / FTPS';
    }

    public function getConfigFields(): array
    {
        return [
            [
                'name'     => 'host',
                'type'     => 'text',
                'label'    => 'Host',
                'required' => true,
            ],
            [
                'name'     => 'port',
                'type'     => 'text',
                'label'    => 'Port',
                'required' => false,
                'default'  => '22',
            ],
            [
                'name'     => 'username',
                'type'     => 'text',
                'label'    => 'Username',
                'required' => true,
            ],
            [
                'name'     => 'password',
                'type'     => 'text',
                'label'    => 'Password (direct, avoid in production)',
                'required' => false,
            ],
            [
                'name'        => 'passwordRef',
                'type'        => 'text',
                'label'       => 'Password reference (env:... or file:...)',
                'required'    => false,
                'placeholder' => 'env:OUTBOX_SFTP_PASSWORD',
            ],
            [
                'name'        => 'privateKey',
                'type'        => 'text',
                'label'       => 'Private key path (direct, avoid in production)',
                'required'    => false,
                'placeholder' => '/run/secrets/sftp_id_rsa',
            ],
            [
                'name'        => 'privateKeyRef',
                'type'        => 'text',
                'label'       => 'Private key reference (env:... or file:...)',
                'required'    => false,
                'placeholder' => 'file:/run/secrets/sftp_id_rsa',
            ],
            [
                'name'     => 'passphrase',
                'type'     => 'text',
                'label'    => 'Private key passphrase (optional)',
                'required' => false,
            ],
            [
                'name'        => 'remoteDir',
                'type'        => 'text',
                'label'       => 'Remote directory',
                'required'    => true,
                'placeholder' => '/incoming/shopware',
            ],
            [
                'name'        => 'fileNamePattern',
                'type'        => 'text',
                'label'       => 'Filename pattern',
                'required'    => false,
                'default'     => '{eventId}.json',
                'placeholder' => '{eventName}-{eventId}.json',
            ],
        ];
    }

    public function validateConfig(array $config): void
    {
        $required = ['host', 'username', 'remoteDir'];

        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw new \RuntimeException(sprintf('SFTP destination requires "%s" config.', $key));
            }
        }

        $password   = $config['password'];
        $privateKey = $config['privateKey'];

        if (empty($password) && empty($privateKey)) {
            throw new \RuntimeException('SFTP destination requires either "password/passwordRef" or "privateKey/privateKeyRef" config.');
        }
    }

    public function publish(DomainEvent $event, array $context, array $config): void
    {
        $this->validateConfig($config);

        $host            = (string) $config['host'];
        $port            = (int) $config['port'];
        $username        = (string) $config['username'];
        $password        = (string) $config['password'];
        $privateKey      = (string) $config['privateKey'];
        $passphrase      = (string) $config['passphrase'];
        $remoteDir       = (string) $config['remoteDir'];
        $fileNamePattern = (string) $config['fileNamePattern'];
        $fileName        = $this->resolveFileName($fileNamePattern, $event);

        $payload = json_encode([
            'deliveryId'     => $context['deliveryId'],
            'destinationId'  => $context['id'],
            'destinationKey' => $context['key'],
            'event'          => $event->toArray(),
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);

        $connectionProvider = new SftpConnectionProvider(
            $host,
            $username,
            $password ?: null,
            $privateKey ?: null,
            $passphrase ?: null,
            $port
        );

        $adapter    = new SftpAdapter($connectionProvider, $remoteDir);
        $filesystem = new Filesystem($adapter);

        try {
            $filesystem->write(ltrim($fileName, '/'), $payload);
        } catch (FilesystemException $e) {
            throw new \RuntimeException(sprintf('Could not write payload to remote SFTP file "%s".', $fileName), 0, $e);
        }
    }

    private function resolveFileName(string $pattern, DomainEvent $event): string
    {
        $effectivePattern = $pattern !== '' ? $pattern : '{eventId}.json';

        return strtr($effectivePattern, [
            '{eventId}'     => $event->getId(),
            '{eventName}'   => str_replace('.', '_', $event->getEventName()),
            '{aggregateId}' => $event->getAggregateId(),
        ]);
    }
}
