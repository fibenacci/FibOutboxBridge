<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Content\OutboxTarget;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OutboxTargetEntity>
 */
class OutboxTargetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OutboxTargetEntity::class;
    }
}
