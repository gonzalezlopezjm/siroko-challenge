<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Http;

use App\Cart\Application\Query\CartDTO;
use App\Cart\Application\Query\CartItemDTO;
use App\Cart\Application\Query\GetCart\GetCartQuery;
use App\Cart\Domain\Exception\CartNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/carts/{cartId}', methods: ['GET'])]
final class GetCartController
{
    public function __construct(private readonly MessageBusInterface $queryBus) {}

    public function __invoke(string $cartId): JsonResponse
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetCartQuery($cartId));
            /** @var CartDTO $dto */
            $dto = $envelope->last(HandledStamp::class)->getResult();

            return new JsonResponse($this->serialize($dto));
        } catch (HandlerFailedException $e) {
            $cause = $e->getPrevious() ?? $e;
            if ($cause instanceof CartNotFoundException) {
                return $this->notFound($cause->getMessage());
            }
            if ($cause instanceof \InvalidArgumentException) {
                return new JsonResponse(['error' => ['code' => 'invalid_id', 'message' => $cause->getMessage()]], Response::HTTP_BAD_REQUEST);
            }
            throw $e;
        }
    }

    private function serialize(CartDTO $dto): array
    {
        return [
            'id'         => $dto->id,
            'customerId' => $dto->customerId,
            'items'      => array_map(fn (CartItemDTO $i) => [
                'productId'   => $i->productId,
                'productName' => $i->productName,
                'unitPrice'   => ['amount' => $i->unitPriceAmount, 'currency' => $i->unitPriceCurrency],
                'quantity'    => $i->quantity,
                'subtotal'    => ['amount' => $i->subtotalAmount, 'currency' => $i->subtotalCurrency],
            ], $dto->items),
            'total'      => ['amount' => $dto->totalAmount, 'currency' => $dto->totalCurrency],
            'createdAt'  => $dto->createdAt,
            'updatedAt'  => $dto->updatedAt,
        ];
    }

    private function notFound(string $message): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => 'cart_not_found', 'message' => $message]], Response::HTTP_NOT_FOUND);
    }
}
