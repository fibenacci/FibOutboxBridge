<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Flow\Service;

use Doctrine\DBAL\Connection;

class OutboxDestinationSelector
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<array{id: string, key: string, type: string, config: array<string, mixed>}>
     */
    public function getActiveDestinationsByType(string $type): array
    {
        $normalizedType = $type;

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
            $id              = (string) ($row['id'] ?? '');
            $key             = (string) ($row['technical_name'] ?? '');
            $destinationType = (string) ($row['type'] ?? '');
            $config          = $this->decodeJsonConfig($row, 'config');

            if ($id === '' || $key === '' || $destinationType === '') {
                continue;
            }

            $destinations[] = [
                'id'     => $id,
                'key'    => $key,
                'type'   => $destinationType,
                'config' => $config,
            ];
        }

        return $destinations;
    }

    /**
     * @return null|array{id: string, key: string, type: string, config: array<string, mixed>}
     */
    public function getActiveDestinationById(string $destinationId, ?string $expectedType = null): ?array
    {
        $normalizedId = $destinationId;

        if ($normalizedId === '') {
            return null;
        }

        $normalizedType = (string) $expectedType;

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

        if (empty($row) || $row !== (array) $row) {
            return null;
        }

        $id     = (string) ($row['id'] ?? '');
        $key    = (string) ($row['technical_name'] ?? '');
        $type   = (string) ($row['type'] ?? '');
        $config = $this->decodeJsonConfig($row, 'config');

        if ($id === '' || $key === '' || $type === '') {
            return null;
        }

        if ($normalizedType !== '' && $type !== $normalizedType) {
            return null;
        }

        return [
            'id'     => $id,
            'key'    => $key,
            'type'   => $type,
            'config' => $config,
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function decodeJsonConfig(array $row, string $key): array
    {
        if (empty($row[$key])) {
            return [];
        }

        $decoded = json_decode((string) $row[$key], true);

        return $decoded === (array) $decoded ? $decoded : [];
    }
}
