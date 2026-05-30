<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order;

use App\Cart\Application\Command\AddItemToCart\AddItemToCartCommand;
use App\Cart\Application\Command\CreateCart\CreateCartCommand;
use App\Catalog\Application\Command\CreateProduct\CreateProductCommand;
use App\Order\Application\Command\CancelOrder\CancelOrderCommand;
use App\Order\Application\Command\CheckoutOrder\CheckoutOrderCommand;
use App\Order\Application\Query\GetOrder\GetOrderQuery;
use App\Order\Application\Query\OrderDTO;
use App\Order\Domain\Exception\CartIsEmptyException;
use App\Order\Domain\Exception\InsufficientStockException;
use App\Order\Domain\Exception\OrderAlreadyCancelledException;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class CheckoutOrderHandlerTest extends IntegrationTestCase
{
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bus = self::getContainer()->get(MessageBusInterface::class);
    }

    public function testCheckoutCreatesOrder(): void
    {
        $cartId = $this->createCartWithProduct(2);

        $orderId = $this->dispatch($this->bus, $this->checkoutCommand($cartId));

        self::assertNotEmpty($orderId);

        /** @var OrderDTO $dto */
        $dto = $this->dispatch($this->bus, new GetOrderQuery($orderId));

        self::assertSame($orderId, $dto->id);
        self::assertSame('PENDING', $dto->status);
        self::assertCount(1, $dto->lines);
        self::assertSame(2, $dto->lines[0]->quantity);
        self::assertSame(4999 * 2, $dto->totalAmount);
    }

    public function testCheckoutDecreasesStock(): void
    {
        $productId = $this->createProduct(stock: 5);

        // Create all carts BEFORE any checkout so stock is still available when adding items
        $cartId1 = $this->dispatch($this->bus, new CreateCartCommand());
        $this->dispatch($this->bus, new AddItemToCartCommand($cartId1, $productId, 3));

        $cartId2 = $this->dispatch($this->bus, new CreateCartCommand());
        $this->dispatch($this->bus, new AddItemToCartCommand($cartId2, $productId, 2));

        $cartId3 = $this->dispatch($this->bus, new CreateCartCommand());
        $this->dispatch($this->bus, new AddItemToCartCommand($cartId3, $productId, 1));

        // First checkout: stock 5 → 2
        $this->dispatch($this->bus, $this->checkoutCommand($cartId1));
        // Second checkout: stock 2 → 0
        $orderId2 = $this->dispatch($this->bus, $this->checkoutCommand($cartId2));
        self::assertNotEmpty($orderId2);

        // Third checkout: stock is 0, 1 requested → insufficient stock
        $this->expectException(InsufficientStockException::class);
        $this->dispatch($this->bus, $this->checkoutCommand($cartId3));
    }

    public function testCheckoutEmptyCartThrows(): void
    {
        $cartId = $this->dispatch($this->bus, new CreateCartCommand());

        $this->expectException(CartIsEmptyException::class);
        $this->dispatch($this->bus, $this->checkoutCommand($cartId));
    }

    public function testCheckoutInsufficientStockThrows(): void
    {
        $productId = $this->createProduct(stock: 1);
        $cartId    = $this->dispatch($this->bus, new CreateCartCommand());
        $this->dispatch($this->bus, new AddItemToCartCommand($cartId, $productId, 1));

        // Deplete stock via another order
        $cartId2 = $this->dispatch($this->bus, new CreateCartCommand());
        $this->dispatch($this->bus, new AddItemToCartCommand($cartId2, $productId, 1));
        $this->dispatch($this->bus, $this->checkoutCommand($cartId2));

        // Original cart now has insufficient stock
        $this->expectException(InsufficientStockException::class);
        $this->dispatch($this->bus, $this->checkoutCommand($cartId));
    }

    public function testCancelOrderSucceeds(): void
    {
        $cartId  = $this->createCartWithProduct(1);
        $orderId = $this->dispatch($this->bus, $this->checkoutCommand($cartId));

        $this->dispatch($this->bus, new CancelOrderCommand($orderId));

        /** @var OrderDTO $dto */
        $dto = $this->dispatch($this->bus, new GetOrderQuery($orderId));
        self::assertSame('CANCELLED', $dto->status);
    }

    public function testCancelAlreadyCancelledOrderThrows(): void
    {
        $cartId  = $this->createCartWithProduct(1);
        $orderId = $this->dispatch($this->bus, $this->checkoutCommand($cartId));
        $this->dispatch($this->bus, new CancelOrderCommand($orderId));

        $this->expectException(OrderAlreadyCancelledException::class);
        $this->dispatch($this->bus, new CancelOrderCommand($orderId));
    }

    private function createCartWithProduct(int $quantity = 1): string
    {
        $productId = $this->createProduct();
        $cartId    = $this->dispatch($this->bus, new CreateCartCommand());
        $this->dispatch($this->bus, new AddItemToCartCommand($cartId, $productId, $quantity));

        return $cartId;
    }

    private function createProduct(int $stock = 10): string
    {
        static $counter = 0;
        ++$counter;

        return $this->dispatch($this->bus, new CreateProductCommand(
            name: "Producto Test {$counter}",
            description: 'Desc',
            priceAmount: 4999,
            priceCurrency: 'EUR',
            category: 'CYCLING',
            brand: 'Mizuno',
            attributes: [],
            stock: $stock,
            imageUrl: null,
        ));
    }

    private function checkoutCommand(string $cartId): CheckoutOrderCommand
    {
        return new CheckoutOrderCommand(
            cartId: $cartId,
            customerId: null,
            customerEmail: null,
            shippingStreet: 'Calle Mayor 12',
            shippingCity: 'Madrid',
            shippingPostalCode: '28001',
            shippingCountry: 'ES',
        );
    }
}
