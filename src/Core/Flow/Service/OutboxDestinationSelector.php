<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Flow\Service;

use Doctrine\DBAL\Connection;

class OutboxDestinationSelector
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @return list<array{id: string, key: string, type: string, config: array<string, mixed>}>
     */
    public function getActiveDestinationsByType(string $type): array
    {
        $normalizedType = strtolower(trim($type));
        if ($normalizedType === '') {
            return [];
        }

        try {
            $rows = $this->connection->fetchAllAssociative(<<<SQL
                SELECT `id`, `technical_name`, `type`, `config`
                FROM `fib_outbox_destination`
                WHERE `is_active` = 1
                  AND `type` = :type
                ORDER BY `name` ASC, `id` ASC
            SQL, [
                'type' => $normalizedType,
            ]);
        } catch (\Throwable) {
            $rows = $this->connection->fetchAllAssociative(<<<SQL
                SELECT `id`, `technical_name`, `type`, `config`
                FROM `fib_outbox_target`
                WHERE `is_active` = 1
                  AND `type` = :type
                ORDER BY `name` ASC, `id` ASC
            SQL, [
                'type' => $normalizedType,
            ]);
        }

        $destinations = [];

        foreach ($rows as $row) {
            $id = trim((string) ($row['id'] ?? ''));
            $key = trim((string) ($row['technical_name'] ?? ''));
            $destinationType = strtolower(trim((string) ($row['type'] ?? '')));
            $config = json_decode((string) ($row['config'] ?? '{}'), true);

            if ($id === '' || $key === '' || $destinationType === '') {
                continue;
            }

            $destinations[] = [
                'id' => $id,
                'key' => $key,
                'type' => $destinationType,
                'config' => is_array($config) ? $config : [],
            ];
        }

        return $destinations;
    }

    /**
     * @return array{id: string, key: string, type: string, config: array<string, mixed>}|null
     */
    public function getActiveDestinationById(string $destinationId, ?string $expectedType = null): ?array
    {
        $normalizedId = strtolower(trim($destinationId));
        if ($normalizedId === '') {
            return null;
        }

        $normalizedType = strtolower(trim((string) $expectedType));

        try {
            $row = $this->connection->fetchAssociative(<<<SQL
                SELECT `id`, `technical_name`, `type`, `config`
                FROM `fib_outbox_destination`
                WHERE `is_active` = 1
                  AND LOWER(`id`) = :id
                LIMIT 1
            SQL, [
                'id' => $normalizedId,
            ]);
        } catch (\Throwable) {
            $row = $this->connection->fetchAssociative(<<<SQL
                SELECT `id`, `technical_name`, `type`, `config`
                FROM `fib_outbox_target`
                WHERE `is_active` = 1
                  AND LOWER(`id`) = :id
                LIMIT 1
            SQL, [
                'id' => $normalizedId,
            ]);
        }

        if (!is_array($row)) {
            return null;
        }

        $id = trim((string) ($row['id'] ?? ''));
        $key = trim((string) ($row['technical_name'] ?? ''));
        $type = strtolower(trim((string) ($row['type'] ?? '')));
        $config = json_decode((string) ($row['config'] ?? '{}'), true);

        if ($id === '' || $key === '' || $type === '') {
            return null;
        }

        if ($normalizedType !== '' && $type !== $normalizedType) {
            return null;
        }

        return [
            'id' => $id,
            'key' => $key,
            'type' => $type,
            'config' => is_array($config) ? $config : [],
        ];
    }
}
