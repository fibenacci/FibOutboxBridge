<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Controller\Api;

use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;
use Fib\OutboxBridge\Core\Outbox\Service\OutboxDispatcher;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class OutboxActionController extends AbstractController
{
    public function __construct(
        private readonly OutboxDispatcher $dispatcher,
        private readonly OutboxRepository $repository
    ) {
    }

    #[Route(
        path: '/api/_action/fib-outbox/dispatch',
        name: 'api.action.fib_outbox.dispatch',
        defaults: ['_acl' => ['order.editor']],
        methods: ['POST']
    )]
    public function dispatch(int $limit, string $workerId = 'admin'): JsonResponse
    {
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
    public function requeueDead(int $limit, string $eventName): JsonResponse
    {
        return new JsonResponse([
            'data' => [
                'requeued' => $this->repository->requeueDead($limit, $eventName),
            ],
        ]);
    }
}
