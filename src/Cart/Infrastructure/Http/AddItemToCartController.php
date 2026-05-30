<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Http;

use App\Cart\Application\Command\AddItemToCart\AddItemToCartCommand;
use App\Cart\Domain\Exception\CartLineItemLimitExceededException;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Exception\InsufficientStockException;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/carts/{cartId}/items', methods: ['POST'])]
final class AddItemToCartController
{
    public function __construct(private readonly MessageBusInterface $commandBus) {}

    public function __invoke(Request $request, string $cartId): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body) || !isset($body['productId'], $body['quantity'])) {
            return new JsonResponse(
                ['error' => ['code' => 'missing_field', 'message' => 'Fields "productId" and "quantity" are required.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $this->commandBus->dispatch(new AddItemToCartCommand(
                cartId: $cartId,
                productId: (string) $body['productId'],
                quantity: (int) $body['quantity'],
            ));

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $e) {
            return $this->handleDomainException($e->getPrevious() ?? $e);
        } catch (\InvalidArgumentException $e) {
            return $this->handleDomainException($e);
        }
    }

    private function handleDomainException(\Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof CartNotFoundException              => new JsonResponse(['error' => ['code' => 'cart_not_found', 'message' => $e->getMessage()]], Response::HTTP_NOT_FOUND),
            $e instanceof ProductNotFoundException           => new JsonResponse(['error' => ['code' => 'product_not_found', 'message' => $e->getMessage()]], Response::HTTP_NOT_FOUND),
            $e instanceof InsufficientStockException        => new JsonResponse(['error' => ['code' => 'insufficient_stock', 'message' => $e->getMessage(), 'context' => ['productId' => $e->productId(), 'available' => $e->available(), 'requested' => $e->requested()]]], Response::HTTP_UNPROCESSABLE_ENTITY),
            $e instanceof CartLineItemLimitExceededException => new JsonResponse(['error' => ['code' => 'cart_lines_limit_exceeded', 'message' => $e->getMessage()]], Response::HTTP_UNPROCESSABLE_ENTITY),
            $e instanceof \InvalidArgumentException         => new JsonResponse(['error' => ['code' => 'invalid_id', 'message' => $e->getMessage()]], Response::HTTP_BAD_REQUEST),
            default                                         => throw $e,
        };
    }
}
