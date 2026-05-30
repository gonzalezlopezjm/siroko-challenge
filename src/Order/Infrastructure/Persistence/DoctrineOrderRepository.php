<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence;

use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Model\OrderLineId;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\Model\ShippingAddress;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Infrastructure\Persistence\Model\OrderOrm;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineOrderRepository implements OrderRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Order $order): void
    {
        $orm = $this->em->find(OrderOrm::class, $order->id()->value());

        if ($orm === null) {
            $orm = $this->fromDomain($order);
            $this->em->persist($orm);
        } else {
            $this->updateOrm($orm, $order);
        }

        $this->em->flush();
    }

    public function findById(OrderId $id): ?Order
    {
        $orm = $this->em->find(OrderOrm::class, $id->value());

        if ($orm === null) {
            return null;
        }

        return $this->toDomain($orm);
    }

    private function toDomain(OrderOrm $orm): Order
    {
        $lines = array_map(
            static function (array $data): OrderLine {
                return OrderLine::reconstitute(
                    id: OrderLineId::fromString($data['id']),
                    productId: $data['productId'],
                    productName: $data['productName'],
                    unitPriceAmount: (int) $data['unitPriceAmount'],
                    unitPriceCurrency: $data['unitPriceCurrency'],
                    quantity: (int) $data['quantity'],
                );
            },
            json_decode($orm->linesJson, true) ?? [],
        );

        return Order::reconstitute(
            id: OrderId::fromString($orm->id),
            customerId: $orm->customerId,
            customerEmail: $orm->customerEmail,
            lines: $lines,
            totalAmount: $orm->totalAmount,
            totalCurrency: $orm->totalCurrency,
            status: OrderStatus::from($orm->status),
            shippingAddress: ShippingAddress::of(
                $orm->shippingStreet,
                $orm->shippingCity,
                $orm->shippingPostalCode,
                $orm->shippingCountry,
            ),
            createdAt: $orm->createdAt,
        );
    }

    private function fromDomain(Order $order): OrderOrm
    {
        $orm                    = new OrderOrm();
        $orm->id                = $order->id()->value();
        $orm->customerId        = $order->customerId();
        $orm->customerEmail     = $order->customerEmail();
        $orm->totalAmount       = $order->totalAmount();
        $orm->totalCurrency     = $order->totalCurrency();
        $orm->status            = $order->status()->value;
        $orm->shippingStreet    = $order->shippingAddress()->street();
        $orm->shippingCity      = $order->shippingAddress()->city();
        $orm->shippingPostalCode = $order->shippingAddress()->postalCode();
        $orm->shippingCountry   = $order->shippingAddress()->country();
        $orm->linesJson         = $this->serializeLines($order);
        $orm->createdAt         = $order->createdAt();

        return $orm;
    }

    private function updateOrm(OrderOrm $orm, Order $order): void
    {
        $orm->status = $order->status()->value;
    }

    private function serializeLines(Order $order): string
    {
        return json_encode(array_map(
            static fn (OrderLine $line) => [
                'id'                => $line->id()->value(),
                'productId'         => $line->productId(),
                'productName'       => $line->productName(),
                'unitPriceAmount'   => $line->unitPriceAmount(),
                'unitPriceCurrency' => $line->unitPriceCurrency(),
                'quantity'          => $line->quantity(),
            ],
            $order->lines(),
        ));
    }
}
