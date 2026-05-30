<?php

declare(strict_types=1);

namespace App\Order\Application\Command\CheckoutOrder;

use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Order\Domain\Event\OrderCreated;
use App\Order\Domain\Exception\CartIsEmptyException;
use App\Order\Domain\Exception\InsufficientStockException;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Model\OrderLineId;
use App\Order\Domain\Model\ShippingAddress;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class CheckoutOrderHandler
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $eventBus,
    ) {}

    public function __invoke(CheckoutOrderCommand $command): string
    {
        $cartId = CartId::fromString($command->cartId);
        $cart   = $this->cartRepository->findById($cartId);

        if ($cart === null) {
            throw CartNotFoundException::withId($cartId);
        }

        if ($cart->isEmpty()) {
            throw CartIsEmptyException::withCartId($command->cartId);
        }

        return $this->em->wrapInTransaction(function () use ($command, $cart): string {
            $lines = [];

            foreach ($cart->items() as $item) {
                $productId = ProductId::fromString($item->productId());
                $product   = $this->productRepository->findById($productId);

                if ($product === null) {
                    throw new \RuntimeException(sprintf('Product "%s" not found during checkout.', $item->productId()));
                }

                $available = $product->stock()->quantity();
                $requested = $item->quantity();

                if ($available < $requested) {
                    throw InsufficientStockException::forProduct(
                        productId: $product->id()->value(),
                        productName: $product->name()->value(),
                        available: $available,
                        requested: $requested,
                    );
                }

                $lines[] = OrderLine::create(
                    id: OrderLineId::generate(),
                    productId: $item->productId(),
                    productName: $item->productName(),
                    unitPriceAmount: $item->unitPriceAmount(),
                    unitPriceCurrency: $item->unitPriceCurrency(),
                    quantity: $item->quantity(),
                );

                $product->decreaseStock($requested);
                $this->productRepository->save($product);
            }

            $orderId = OrderId::generate();
            $order   = Order::create(
                id: $orderId,
                customerId: $command->customerId,
                customerEmail: $command->customerEmail,
                lines: $lines,
                shippingAddress: ShippingAddress::of(
                    $command->shippingStreet,
                    $command->shippingCity,
                    $command->shippingPostalCode,
                    $command->shippingCountry,
                ),
            );

            $this->orderRepository->save($order);

            $this->eventBus->dispatch(new OrderCreated(
                orderId: $orderId->value(),
                customerId: $command->customerId,
                customerEmail: $command->customerEmail,
                totalAmount: $order->totalAmount(),
                totalCurrency: $order->totalCurrency(),
                lines: array_map(
                    fn (OrderLine $l) => [
                        'productName'      => $l->productName(),
                        'quantity'         => $l->quantity(),
                        'unitPriceAmount'  => $l->unitPriceAmount(),
                        'unitPriceCurrency'=> $l->unitPriceCurrency(),
                        'subtotalAmount'   => $l->subtotalAmount(),
                    ],
                    $order->lines(),
                ),
                shippingStreet: $command->shippingStreet,
                shippingCity: $command->shippingCity,
                shippingPostalCode: $command->shippingPostalCode,
                shippingCountry: $command->shippingCountry,
            ));

            return $orderId->value();
        });
    }
}
