<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Subscriber;

use Doctrine\DBAL\Connection;
use Fib\OutboxBridge\Core\Content\OutboxDestination\OutboxDestinationDefinition;
use Fib\OutboxBridge\Core\Content\OutboxDestination\OutboxDestinationEntity;
use Fib\OutboxBridge\Core\Outbox\Security\OutboxSecretConfigMasker;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OutboxDestinationSecuritySubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly OutboxSecretConfigMasker $secretConfigMasker,
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OutboxDestinationDefinition::ENTITY_NAME . '.loaded' => 'onDestinationLoaded',
            PreWriteValidationEvent::class                       => 'onPreWriteValidation',
        ];
    }

    /**
     * @param EntityLoadedEvent<OutboxDestinationEntity> $event
     */
    public function onDestinationLoaded(EntityLoadedEvent $event): void
    {
        if (!$event->getContext()->getSource() instanceof AdminApiSource) {
            return;
        }

        foreach ($event->getEntities() as $entity) {
            if (!$entity instanceof OutboxDestinationEntity) {
                continue;
            }

            $config = $entity->getConfig();

            if ($config !== (array) $config) {
                continue;
            }

            $entity->setConfig($this->secretConfigMasker->maskConfig($config));
        }
    }

    public function onPreWriteValidation(PreWriteValidationEvent $event): void
    {
        if (!$event->getContext()->getSource() instanceof AdminApiSource) {
            return;
        }

        foreach ($event->getCommands() as $command) {
            if ($command->getEntityName() !== OutboxDestinationDefinition::ENTITY_NAME) {
                continue;
            }

            $payload   = $command->getPayload();
            $newConfig = $payload['config'] ?? null;

            if ($newConfig !== (array) $newConfig) {
                continue;
            }

            $decodedPrimaryKey = $command->getDecodedPrimaryKey();
            $destinationId     = (string) ($decodedPrimaryKey['id'] ?? '');

            if ($destinationId === '' || !Uuid::isValid($destinationId)) {
                continue;
            }

            $existingConfig = $this->loadExistingConfig($destinationId);

            if ($existingConfig === []) {
                continue;
            }

            $mergedConfig = $this->secretConfigMasker->restoreMaskedPlaceholders($newConfig, $existingConfig);
            $command->addPayload('config', $mergedConfig);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadExistingConfig(string $destinationId): array
    {
        $rawConfig = $this->connection->fetchOne(
            'SELECT `config` FROM `fib_outbox_destination` WHERE `id` = :id LIMIT 1',
            ['id' => Uuid::fromHexToBytes($destinationId)]
        );

        if (empty($rawConfig)) {
            return [];
        }

        $decoded = json_decode((string) $rawConfig, true);

        return $decoded === (array) $decoded ? $decoded : [];
    }
}
