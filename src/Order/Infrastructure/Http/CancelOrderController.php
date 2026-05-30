<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http;

use App\Order\Application\Command\CancelOrder\CancelOrderCommand;
use App\Order\Domain\Exception\OrderAlreadyCancelledException;
use App\Order\Domain\Exception\OrderNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders/{orderId}/cancel', methods: ['POST'])]
final class CancelOrderController
{
    public function __construct(private readonly MessageBusInterface $commandBus) {}

    public function __invoke(string $orderId): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new CancelOrderCommand($orderId));

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $e) {
            $cause = $e->getPrevious() ?? $e;
            if ($cause instanceof OrderNotFoundException) {
                return new JsonResponse(['error' => ['code' => 'order_not_found', 'message' => $cause->getMessage()]], Response::HTTP_NOT_FOUND);
            }
            if ($cause instanceof OrderAlreadyCancelledException) {
                return new JsonResponse(['error' => ['code' => 'order_already_cancelled', 'message' => $cause->getMessage()]], Response::HTTP_CONFLICT);
            }
            if ($cause instanceof \InvalidArgumentException) {
                return new JsonResponse(['error' => ['code' => 'invalid_id', 'message' => $cause->getMessage()]], Response::HTTP_BAD_REQUEST);
            }
            throw $e;
        }
    }
}
