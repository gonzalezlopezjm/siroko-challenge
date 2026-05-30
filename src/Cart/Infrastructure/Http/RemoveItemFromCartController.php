<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Http;

use App\Cart\Application\Command\RemoveItemFromCart\RemoveItemFromCartCommand;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Exception\ItemNotInCartException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/carts/{cartId}/items/{productId}', methods: ['DELETE'])]
final class RemoveItemFromCartController
{
    public function __construct(private readonly MessageBusInterface $commandBus) {}

    public function __invoke(string $cartId, string $productId): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new RemoveItemFromCartCommand($cartId, $productId));

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $e) {
            $cause = $e->getPrevious() ?? $e;
            if ($cause instanceof CartNotFoundException) {
                return new JsonResponse(['error' => ['code' => 'cart_not_found', 'message' => $cause->getMessage()]], Response::HTTP_NOT_FOUND);
            }
            if ($cause instanceof ItemNotInCartException) {
                return new JsonResponse(['error' => ['code' => 'item_not_found', 'message' => $cause->getMessage()]], Response::HTTP_NOT_FOUND);
            }
            throw $e;
        }
    }
}
