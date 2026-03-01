<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Content\OutboxEvent;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class OutboxEventDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'fib_outbox_event';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return OutboxEventEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OutboxEventCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('id', 'id', 32))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new StringField('event_name', 'eventName', 191))->addFlags(new ApiAware(), new Required()),
            (new StringField('aggregate_type', 'aggregateType', 100))->addFlags(new ApiAware(), new Required()),
            (new StringField('aggregate_id', 'aggregateId', 100))->addFlags(new ApiAware(), new Required()),
            (new JsonField('payload', 'payload'))->addFlags(new ApiAware(), new Required()),
            (new JsonField('meta', 'meta'))->addFlags(new ApiAware()),
            (new DateTimeField('occurred_at', 'occurredAt'))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('available_at', 'availableAt'))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('published_at', 'publishedAt'))->addFlags(new ApiAware()),
            (new StringField('status', 'status', 16))->addFlags(new ApiAware(), new Required()),
            (new IntField('attempts', 'attempts'))->addFlags(new ApiAware(), new Required()),
            (new DateTimeField('locked_until', 'lockedUntil'))->addFlags(new ApiAware()),
            (new StringField('lock_owner', 'lockOwner', 128))->addFlags(new ApiAware()),
            (new LongTextField('last_error', 'lastError'))->addFlags(new ApiAware()),
            (new CreatedAtField())->addFlags(new ApiAware()),
            (new UpdatedAtField())->addFlags(new ApiAware()),
        ]);
    }
}
