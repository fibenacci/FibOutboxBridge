<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\ScheduledTask;

use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

class DispatchOutboxTask extends ScheduledTask
{
    public static function getTaskName(): string
    {
        return 'fib_outbox_bridge.dispatch_outbox';
    }

    public static function getDefaultInterval(): int
    {
        return self::MINUTELY;
    }

    public static function shouldRescheduleOnFailure(): bool
    {
        return true;
    }
}
