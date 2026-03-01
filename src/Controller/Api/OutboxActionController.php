<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Controller\Api;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyRegistry;
use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;
use Fib\OutboxBridge\Core\Outbox\Service\OutboxDispatcher;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class OutboxActionController extends AbstractController
{
    public function __construct(
        private readonly OutboxDispatcher $dispatcher,
        private readonly OutboxRepository $repository,
        private readonly OutboxDestinationStrategyRegistry $destinationStrategyRegistry,
    ) {
    }

    #[Route(
        path: '/api/_action/fib-outbox/dispatch',
        name: 'api.action.fib_outbox.dispatch',
        defaults: ['_acl' => ['order.editor']],
        methods: ['POST']
    )]
    public function dispatch(RequestDataBag $dataBag): JsonResponse
    {
        $limit    = $dataBag->getInt('limit', 100);
        $workerId = $dataBag->getString('workerId', 'admin');

        $result = $this->dispatcher->dispatchBatch($limit, $workerId);

        return new JsonResponse([
            'data' => $result,
        ]);
    }

    #[Route(
        path: '/api/_action/fib-outbox/reset-stuck',
        name: 'api.action.fib_outbox.reset_stuck',
        defaults: ['_acl' => ['order.editor']],
        methods: ['POST']
    )]
    public function resetStuck(): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'reset' => $this->repository->resetExpiredProcessingLocks(),
            ],
        ]);
    }

    #[Route(
        path: '/api/_action/fib-outbox/requeue-dead',
        name: 'api.action.fib_outbox.requeue_dead',
        defaults: ['_acl' => ['order.editor']],
        methods: ['POST']
    )]
    public function requeueDead(RequestDataBag $dataBag): JsonResponse
    {
        $limit     = $dataBag->getInt('limit', 100);
        $eventName = $dataBag->getString('eventName');

        return new JsonResponse([
            'data' => [
                'requeued' => $this->repository->requeueDead($limit, $eventName ?: null),
            ],
        ]);
    }

    #[Route(
        path: '/api/_action/fib-outbox/destination-types',
        name: 'api.action.fib_outbox.destination_types',
        defaults: ['_acl' => ['fib_outbox_destination:read']],
        methods: ['GET']
    )]
    public function destinationTypes(): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->destinationStrategyRegistry->getTypeDefinitions(),
        ]);
    }
}
