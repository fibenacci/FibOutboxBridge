<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Content\OutboxRoute;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OutboxRouteDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'fib_outbox_route';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OutboxRouteEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OutboxRouteCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('id', 'id', 32))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new StringField('name', 'name', 191))->addFlags(new ApiAware(), new Required()),
            (new StringField('event_pattern', 'eventPattern', 191))->addFlags(new ApiAware(), new Required()),
            (new IntField('priority', 'priority'))->addFlags(new ApiAware(), new Required()),
            (new BoolField('is_active', 'isActive'))->addFlags(new ApiAware(), new Required()),
            (new JsonField('target_keys', 'targetKeys'))->addFlags(new ApiAware(), new Required()),
            (new CreatedAtField())->addFlags(new ApiAware()),
            (new UpdatedAtField())->addFlags(new ApiAware()),
        ]);
    }
}
