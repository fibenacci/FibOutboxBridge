<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Content\OutboxRoute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OutboxRouteEntity>
 */
class OutboxRouteCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OutboxRouteEntity::class;
    }
}
