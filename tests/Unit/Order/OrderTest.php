<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order;

use App\Order\Domain\Exception\OrderAlreadyCancelledException;
use App\Order\Domain\Model\Order;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderLine;
use App\Order\Domain\Model\OrderLineId;
use App\Order\Domain\Model\OrderStatus;
use App\Order\Domain\Model\ShippingAddress;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    private function address(): ShippingAddress
    {
        return ShippingAddress::of('Calle Mayor 1', 'Madrid', '28001', 'ES');
    }

    private function line(int $price = 1000, int $qty = 2): OrderLine
    {
        return OrderLine::create(
            OrderLineId::generate(),
            'prod-1',
            'Producto',
            $price,
            'EUR',
            $qty,
        );
    }

    private function createOrder(?string $email = 'test@test.com'): Order
    {
        return Order::create(
            id: OrderId::generate(),
            customerId: 'customer-1',
            customerEmail: $email,
            lines: [$this->line(1000, 2), $this->line(500, 3)],
            shippingAddress: $this->address(),
        );
    }

    public function testCreateCalculatesTotalFromLines(): void
    {
        // (1000 * 2) + (500 * 3) = 2000 + 1500 = 3500
        $order = $this->createOrder();
        self::assertSame(3500, $order->totalAmount());
        self::assertSame('EUR', $order->totalCurrency());
    }

    public function testCreateStatusIsPending(): void
    {
        $order = $this->createOrder();
        self::assertSame(OrderStatus::PENDING, $order->status());
    }

    public function testCancelChangesStatusToCancelled(): void
    {
        $order = $this->createOrder();
        $order->cancel();
        self::assertSame(OrderStatus::CANCELLED, $order->status());
    }

    public function testCancelAlreadyCancelledOrderThrows(): void
    {
        $order = $this->createOrder();
        $order->cancel();

        $this->expectException(OrderAlreadyCancelledException::class);
        $order->cancel();
    }

    public function testCustomerEmailCanBeNull(): void
    {
        $order = $this->createOrder(null);
        self::assertNull($order->customerEmail());
    }

    public function testLinesAreReturnedInOrder(): void
    {
        $order = $this->createOrder();
        self::assertCount(2, $order->lines());
    }

    public function testShippingAddressIsPreserved(): void
    {
        $order = $this->createOrder();
        self::assertSame('Madrid', $order->shippingAddress()->city());
    }
}
