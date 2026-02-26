<?php declare(strict_types=1);

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
            ],
        ];
    }
}
