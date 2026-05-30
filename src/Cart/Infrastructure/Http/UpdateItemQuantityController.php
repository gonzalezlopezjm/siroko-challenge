<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Http;

use App\Cart\Application\Command\UpdateItemQuantity\UpdateItemQuantityCommand;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Exception\ItemNotInCartException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/carts/{cartId}/items/{productId}', methods: ['PATCH'])]
final class UpdateItemQuantityController
{
    public function __construct(private readonly MessageBusInterface $commandBus) {}

    public function __invoke(Request $request, string $cartId, string $productId): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body) || !isset($body['quantity'])) {
            return new JsonResponse(
                ['error' => ['code' => 'missing_field', 'message' => 'Field "quantity" is required.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $this->commandBus->dispatch(new UpdateItemQuantityCommand(
                cartId: $cartId,
                productId: $productId,
                quantity: (int) $body['quantity'],
            ));

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
