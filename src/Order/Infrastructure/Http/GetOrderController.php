<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http;

use App\Order\Application\Query\GetOrder\GetOrderQuery;
use App\Order\Application\Query\OrderDTO;
use App\Order\Application\Query\OrderLineDTO;
use App\Order\Domain\Exception\OrderNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders/{orderId}', methods: ['GET'])]
final class GetOrderController
{
    public function __construct(private readonly MessageBusInterface $queryBus) {}

    public function __invoke(string $orderId): JsonResponse
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetOrderQuery($orderId));
            /** @var OrderDTO $dto */
            $dto = $envelope->last(HandledStamp::class)->getResult();

            return new JsonResponse($this->serialize($dto));
        } catch (HandlerFailedException $e) {
            $cause = $e->getPrevious() ?? $e;
            if ($cause instanceof OrderNotFoundException) {
                return new JsonResponse(['error' => ['code' => 'order_not_found', 'message' => $cause->getMessage()]], Response::HTTP_NOT_FOUND);
            }
            if ($cause instanceof \InvalidArgumentException) {
                return new JsonResponse(['error' => ['code' => 'invalid_id', 'message' => $cause->getMessage()]], Response::HTTP_BAD_REQUEST);
            }
            throw $e;
        }
    }

    private function serialize(OrderDTO $dto): array
    {
        return [
            'id'         => $dto->id,
            'customerId' => $dto->customerId,
            'lines'      => array_map(fn (OrderLineDTO $l) => [
                'id'          => $l->id,
                'productId'   => $l->productId,
                'productName' => $l->productName,
                'unitPrice'   => ['amount' => $l->unitPriceAmount, 'currency' => $l->unitPriceCurrency],
                'quantity'    => $l->quantity,
                'subtotal'    => ['amount' => $l->subtotalAmount, 'currency' => $l->subtotalCurrency],
            ], $dto->lines),
            'total'           => ['amount' => $dto->totalAmount, 'currency' => $dto->totalCurrency],
            'status'          => $dto->status,
            'shippingAddress' => [
                'street'     => $dto->shippingStreet,
                'city'       => $dto->shippingCity,
                'postalCode' => $dto->shippingPostalCode,
                'country'    => $dto->shippingCountry,
            ],
            'createdAt' => $dto->createdAt,
        ];
    }
}
