<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Content\OutboxEvent;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OutboxEventEntity>
 */
class OutboxEventCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OutboxEventEntity::class;
    }
}
