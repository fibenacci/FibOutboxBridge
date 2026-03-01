<?php

declare(strict_types=1);

namespace Fib\OutboxBridge;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\Plugin;

class FibOutboxBridge extends Plugin
{
    public function enrichPrivileges(): array
    {
        return [
            AclRoleDefinition::ALL_ROLE_KEY => [
                'fib_outbox_event:read',
                'fib_outbox_destination:read',
                'fib_outbox_destination:create',
                'fib_outbox_destination:update',
                'fib_outbox_destination:delete',
                'fib_outbox_target:read',
                'fib_outbox_target:create',
                'fib_outbox_target:update',
                'fib_outbox_target:delete',
                'fib_outbox_route:read',
                'fib_outbox_route:create',
                'fib_outbox_route:update',
                'fib_outbox_route:delete',
            ],
        ];
    }
}
