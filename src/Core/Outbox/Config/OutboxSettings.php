<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Config;

use Shopware\Core\System\SystemConfig\SystemConfigService;

class OutboxSettings
{
    private const CONFIG_PREFIX = 'FibOutboxBridge.config.';

    public function __construct(
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    public function getDispatchBatchSize(): int
    {
        $value = $this->systemConfigService->getInt(self::CONFIG_PREFIX . 'dispatchBatchSize');

        return max(1, min(1000, $value));
    }

    public function getLockSeconds(): int
    {
        $value = $this->systemConfigService->getInt(self::CONFIG_PREFIX . 'lockSeconds');

        return max(5, min(900, $value));
    }

    public function getMaxAttempts(): int
    {
        $value = $this->systemConfigService->getInt(self::CONFIG_PREFIX . 'maxAttempts');

        return max(1, min(100, $value));
    }
}
