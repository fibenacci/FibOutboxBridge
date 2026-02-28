<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination\Strategy;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyInterface;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;

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
                'name' => 'host',
                'type' => 'text',
                'label' => 'Host',
                'required' => true,
            ],
            [
                'name' => 'port',
                'type' => 'text',
                'label' => 'Port',
                'required' => false,
                'default' => '22',
            ],
            [
                'name' => 'username',
                'type' => 'text',
                'label' => 'Username',
                'required' => true,
            ],
            [
                'name' => 'password',
                'type' => 'text',
                'label' => 'Password',
                'required' => true,
            ],
            [
                'name' => 'remoteDir',
                'type' => 'text',
                'label' => 'Remote directory',
                'required' => true,
                'placeholder' => '/incoming/shopware',
            ],
            [
                'name' => 'fileNamePattern',
                'type' => 'text',
                'label' => 'Filename pattern',
                'required' => false,
                'default' => '{eventId}.json',
                'placeholder' => '{eventName}-{eventId}.json',
            ],
        ];
    }

    public function validateConfig(array $config): void
    {
        $required = ['host', 'username', 'password', 'remoteDir'];

        foreach ($required as $key) {
            if (trim((string) ($config[$key] ?? '')) === '') {
                throw new \RuntimeException(sprintf('SFTP destination requires "%s" config.', $key));
            }
        }
    }

    public function publish(DomainEvent $event, array $context, array $config): void
    {
        $this->validateConfig($config);

        if (!function_exists('ssh2_connect')) {
            throw new \RuntimeException('SFTP destination requires PHP extension "ssh2".');
        }

        $host = (string) $config['host'];
        $port = (int) ($config['port'] ?? 22);
        $username = (string) $config['username'];
        $password = (string) $config['password'];
        $remoteDir = rtrim((string) $config['remoteDir'], '/');
        $fileNamePattern = trim((string) ($config['fileNamePattern'] ?? '{eventId}.json'));
        $fileName = $this->resolveFileName($fileNamePattern, $event);
        $remotePath = sprintf('%s/%s', $remoteDir, ltrim($fileName, '/'));

        $connection = ssh2_connect($host, $port);
        if ($connection === false) {
            throw new \RuntimeException(sprintf('Could not connect to SFTP host "%s:%d".', $host, $port));
        }

        $authOk = ssh2_auth_password($connection, $username, $password);
        if ($authOk !== true) {
            throw new \RuntimeException(sprintf('SFTP auth failed for user "%s".', $username));
        }

        $sftp = ssh2_sftp($connection);
        if ($sftp === false) {
            throw new \RuntimeException('Could not initialize SFTP subsystem.');
        }

        $stream = @fopen(sprintf('ssh2.sftp://%d%s', (int) $sftp, $remotePath), 'wb');
        if (!is_resource($stream)) {
            throw new \RuntimeException(sprintf('Could not open remote SFTP file "%s".', $remotePath));
        }

        $payload = json_encode([
            'deliveryId' => $context['deliveryId'],
            'destinationId' => $context['id'],
            'destinationKey' => $context['key'],
            'event' => $event->toArray(),
        ], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR);

        $bytes = fwrite($stream, $payload);
        fclose($stream);

        if ($bytes === false || $bytes <= 0) {
            throw new \RuntimeException(sprintf('Could not write payload to remote SFTP file "%s".', $remotePath));
        }
    }

    private function resolveFileName(string $pattern, DomainEvent $event): string
    {
        $effectivePattern = $pattern !== '' ? $pattern : '{eventId}.json';

        return strtr($effectivePattern, [
            '{eventId}' => $event->getId(),
            '{eventName}' => str_replace('.', '_', $event->getEventName()),
            '{aggregateId}' => $event->getAggregateId(),
        ]);
    }
}
