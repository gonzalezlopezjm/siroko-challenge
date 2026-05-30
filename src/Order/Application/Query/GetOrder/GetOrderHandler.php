<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetOrder;

use App\Order\Application\Query\OrderDTO;
use App\Order\Application\Query\OrderLineDTO;
use App\Order\Domain\Exception\OrderNotFoundException;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetOrderHandler
{
    public function __construct(private readonly OrderRepositoryInterface $repository) {}

    public function __invoke(GetOrderQuery $query): OrderDTO
    {
        $id    = OrderId::fromString($query->orderId);
        $order = $this->repository->findById($id);

        if ($order === null) {
            throw OrderNotFoundException::withId($id);
        }

        return self::toDTO($order);
    }

    public static function toDTO(Order $order): OrderDTO
    {
        $lineDTOs = array_map(
            static fn ($line) => new OrderLineDTO(
                id: $line->id()->value(),
                productId: $line->productId(),
                productName: $line->productName(),
                unitPriceAmount: $line->unitPriceAmount(),
                unitPriceCurrency: $line->unitPriceCurrency(),
                quantity: $line->quantity(),
                subtotalAmount: $line->subtotalAmount(),
                subtotalCurrency: $line->unitPriceCurrency(),
            ),
            $order->lines(),
        );

        return new OrderDTO(
            id: $order->id()->value(),
            customerId: $order->customerId(),
            lines: $lineDTOs,
            totalAmount: $order->totalAmount(),
            totalCurrency: $order->totalCurrency(),
            status: $order->status()->value,
            shippingStreet: $order->shippingAddress()->street(),
            shippingCity: $order->shippingAddress()->city(),
            shippingPostalCode: $order->shippingAddress()->postalCode(),
            shippingCountry: $order->shippingAddress()->country(),
            createdAt: $order->createdAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
