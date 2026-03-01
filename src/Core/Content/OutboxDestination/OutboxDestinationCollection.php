<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Content\OutboxDestination;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OutboxDestinationEntity>
 */
class OutboxDestinationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OutboxDestinationEntity::class;
    }
}
