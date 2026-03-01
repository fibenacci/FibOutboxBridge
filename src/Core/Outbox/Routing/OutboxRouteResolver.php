<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Routing;

use Doctrine\DBAL\Connection;
use Fib\OutboxBridge\Core\Outbox\Flow\OutboxFlowForwardedEvent;

class OutboxRouteResolver
{
    public function __construct(
        private readonly Connection $connection
    ) {
    }

    /**
     * @return list<array{id: string, key: string, type: string, config: array<string, mixed>}>
     */
    public function resolveTargetsForEventName(string $eventName): array
    {
        try {
            $targetsByTechnicalName = $this->loadActiveDestinations();
            $routes = $this->loadActiveRoutes();
        } catch (\Throwable) {
            return [];
        }

        if ($routes === [] || $targetsByTechnicalName === []) {
            return [];
        }

        usort($routes, static fn (array $a, array $b): int => $a['priority'] <=> $b['priority']);

        $matched = [];

        foreach ($routes as $route) {
            if (empty($route['eventPattern'])) {
                continue;
            }

            if (!$this->matchesPattern((string) $route['eventPattern'], $eventName)) {
                continue;
            }

            foreach ($route['targetKeys'] as $targetKey) {
                if (!isset($targetsByTechnicalName[$targetKey])) {
                    continue;
                }

                $matched[$targetKey] = $targetsByTechnicalName[$targetKey];
            }
        }

        if ($matched === []) {
            return [];
        }

        return array_values($matched);
    }

    /**
     * @return list<string>
     */
    public function getConfiguredFlowEventNames(): array
    {
        $names = [OutboxFlowForwardedEvent::DEFAULT_EVENT_NAME];

        try {
            $rows = $this->connection->fetchAllAssociative(<<<SQL
                SELECT `config`
                FROM `fib_outbox_destination`
                WHERE `is_active` = 1
                  AND `type` = :type
            SQL, [
                'type' => 'flow',
            ]);
        } catch (\Throwable) {
            try {
                $rows = $this->connection->fetchAllAssociative(<<<SQL
                    SELECT `config`
                    FROM `fib_outbox_target`
                    WHERE `is_active` = 1
                      AND `type` = :type
                SQL, [
                    'type' => 'flow',
                ]);
            } catch (\Throwable) {
                return $names;
            }
        }

        foreach ($rows as $row) {
            $config = $this->decodeJsonConfig($row, 'config');
            $flowEventName = OutboxFlowForwardedEvent::DEFAULT_EVENT_NAME;
            if (!empty($config['flowEventName'])) {
                $flowEventName = (string) $config['flowEventName'];
            }

            $names[] = $flowEventName;
        }

        return array_values(array_unique($names));
    }

    /**
     * @return array<string, array{id: string, key: string, type: string, config: array<string, mixed>}>
     */
    private function loadActiveDestinations(): array
    {
        try {
            $rows = $this->connection->fetchAllAssociative(<<<SQL
                SELECT `id`, `technical_name`, `type`, `config`
                FROM `fib_outbox_destination`
                WHERE `is_active` = 1
            SQL);
        } catch (\Throwable) {
            $rows = $this->connection->fetchAllAssociative(<<<SQL
                SELECT `id`, `technical_name`, `type`, `config`
                FROM `fib_outbox_target`
                WHERE `is_active` = 1
            SQL);
        }

        $targets = [];

        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            $technicalName = (string) ($row['technical_name'] ?? '');
            $type = (string) ($row['type'] ?? '');

            if ($id === '' || $technicalName === '' || $type === '') {
                continue;
            }

            $config = $this->decodeJsonConfig($row, 'config');

            $targets[$technicalName] = [
                'id' => $id,
                'key' => $technicalName,
                'type' => $type,
                'config' => $config,
            ];
        }

        return $targets;
    }

    /**
     * @return list<array{eventPattern: string, targetKeys: list<string>, priority: int}>
     */
    private function loadActiveRoutes(): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT `event_pattern`, `target_keys`, `priority`
            FROM `fib_outbox_route`
            WHERE `is_active` = 1
        SQL);

        $routes = [];

        foreach ($rows as $row) {
            $eventPattern = (string) ($row['event_pattern'] ?? '');
            if ($eventPattern === '') {
                continue;
            }

            $targetKeysRaw = $this->decodeJsonArray($row, 'target_keys');
            $targetKeys = [];
            foreach ($targetKeysRaw as $targetKey) {
                if (empty($targetKey)) {
                    continue;
                }

                $targetKeys[] = (string) $targetKey;
            }

            if ($targetKeys === []) {
                continue;
            }

            $routes[] = [
                'eventPattern' => $eventPattern,
                'targetKeys' => array_values(array_unique($targetKeys)),
                'priority' => (int) ($row['priority'] ?? 100),
            ];
        }

        return $routes;
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

    /**
     * @param array<string, mixed> $row
     *
     * @return list<mixed>
     */
    private function decodeJsonArray(array $row, string $key): array
    {
        if (empty($row[$key])) {
            return [];
        }

        $decoded = json_decode((string) $row[$key], true);

        return $decoded === (array) $decoded ? $decoded : [];
    }

    private function matchesPattern(string $pattern, string $eventName): bool
    {
        if ($pattern === '' || $pattern === '*') {
            return true;
        }

        $quoted = preg_quote($pattern, '/');
        $quoted = str_replace('\\*', '.*', $quoted);

        return (bool) preg_match('/^' . $quoted . '$/i', $eventName);
    }
}
