<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Subscriber;

use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductStockOutboxSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly OutboxRepository $repository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $writeResult) {
            if (!$writeResult instanceof EntityWriteResult) {
                continue;
            }

            if ($writeResult->getOperation() === EntityWriteResult::OPERATION_DELETE) {
                continue;
            }

            if (!$this->hasStockChangePayload($writeResult)) {
                continue;
            }

            $aggregateId = $this->normalizePrimaryKey($writeResult->getPrimaryKey());
            if (empty($aggregateId)) {
                continue;
            }

            $payload = [
                'operation' => $writeResult->getOperation(),
                'stock' => $writeResult->getProperty('stock'),
                'availableStock' => $writeResult->getProperty('availableStock'),
                'isCloseout' => $writeResult->getProperty('isCloseout'),
            ];

            $meta = [
                'source' => 'shopware.product.written',
                'contextVersionId' => $event->getContext()->getVersionId(),
            ];

            $this->repository->append(DomainEvent::create(
                'catalog.product.stock_changed.v1',
                'product',
                $aggregateId,
                $payload,
                $meta
            ));
        }
    }

    private function hasStockChangePayload(EntityWriteResult $writeResult): bool
    {
        return $writeResult->hasPayload('stock')
            || $writeResult->hasPayload('available_stock')
            || $writeResult->hasPayload('availableStock')
            || $writeResult->hasPayload('is_closeout')
            || $writeResult->hasPayload('isCloseout');
    }

    /**
     * @param array<string, mixed>|string $primaryKey
     */
    private function normalizePrimaryKey(array|string $primaryKey): ?string
    {
        if ($primaryKey !== (array) $primaryKey) {
            return $primaryKey;
        }

        $id = $primaryKey['id'];
        if (empty($id)) {
            return null;
        }

        return $id;
    }
}
