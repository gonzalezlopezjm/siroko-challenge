<?php

declare(strict_types=1);

namespace App\Tests\Integration\Cart;

use App\Cart\Application\Command\AddItemToCart\AddItemToCartCommand;
use App\Cart\Application\Command\ClearCart\ClearCartCommand;
use App\Cart\Application\Command\CreateCart\CreateCartCommand;
use App\Cart\Application\Command\RemoveItemFromCart\RemoveItemFromCartCommand;
use App\Cart\Application\Command\UpdateItemQuantity\UpdateItemQuantityCommand;
use App\Cart\Application\Query\CartDTO;
use App\Cart\Application\Query\GetCart\GetCartQuery;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class CartHandlerTest extends IntegrationTestCase
{
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bus = self::getContainer()->get(MessageBusInterface::class);
    }

    public function testCreateCartReturnsId(): void
    {
        $cartId = $this->dispatch($this->bus, new CreateCartCommand());

        self::assertNotEmpty($cartId);
    }

    public function testGetCartReturnsEmptyCart(): void
    {
        $cartId = $this->dispatch($this->bus, new CreateCartCommand('guest-123'));

        /** @var CartDTO $dto */
        $dto = $this->dispatch($this->bus, new GetCartQuery($cartId));

        self::assertSame($cartId, $dto->id);
        self::assertSame('guest-123', $dto->customerId);
        self::assertEmpty($dto->items);
        self::assertSame(0, $dto->totalAmount);
    }

    public function testGetCartNotFoundThrows(): void
    {
        $this->expectException(CartNotFoundException::class);

        $this->dispatch($this->bus, new GetCartQuery('550e8400-e29b-41d4-a716-446655440000'));
    }

    public function testAddItemToCartPersistsItem(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->dispatch($this->bus, new CreateCartCommand());

        $this->dispatch($this->bus, new AddItemToCartCommand($cartId, $productId, 2));

        /** @var CartDTO $dto */
        $dto = $this->dispatch($this->bus, new GetCartQuery($cartId));

        self::assertCount(1, $dto->items);
        self::assertSame($productId, $dto->items[0]->productId);
        self::assertSame(2, $dto->items[0]->quantity);
        self::assertSame(4999 * 2, $dto->totalAmount);
    }

    public function testAddSameProductIncrementsQuantity(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->dispatch($this->bus, new CreateCartCommand());

        $this->dispatch($this->bus, new AddItemToCartCommand($cartId, $productId, 1));
        $this->dispatch($this->bus, new AddItemToCartCommand($cartId, $productId, 2));

        /** @var CartDTO $dto */
        $dto = $this->dispatch($this->bus, new GetCartQuery($cartId));

        self::assertCount(1, $dto->items);
        self::assertSame(3, $dto->items[0]->quantity);
    }

    public function testUpdateItemQuantity(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->dispatch($this->bus, new CreateCartCommand());
        $this->dispatch($this->bus, new AddItemToCartCommand($cartId, $productId, 2));

        $this->dispatch($this->bus, new UpdateItemQuantityCommand($cartId, $productId, 5));

        /** @var CartDTO $dto */
        $dto = $this->dispatch($this->bus, new GetCartQuery($cartId));
        self::assertSame(5, $dto->items[0]->quantity);
    }

    public function testRemoveItemFromCart(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->dispatch($this->bus, new CreateCartCommand());
        $this->dispatch($this->bus, new AddItemToCartCommand($cartId, $productId, 1));

        $this->dispatch($this->bus, new RemoveItemFromCartCommand($cartId, $productId));

        /** @var CartDTO $dto */
        $dto = $this->dispatch($this->bus, new GetCartQuery($cartId));
        self::assertEmpty($dto->items);
    }

    public function testClearCart(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->dispatch($this->bus, new CreateCartCommand());
        $this->dispatch($this->bus, new AddItemToCartCommand($cartId, $productId, 1));

        $this->dispatch($this->bus, new ClearCartCommand($cartId));

        /** @var CartDTO $dto */
        $dto = $this->dispatch($this->bus, new GetCartQuery($cartId));
        self::assertEmpty($dto->items);
        self::assertSame(0, $dto->totalAmount);
    }

    private function createProduct(string $name = 'Mallot Mizuno'): string
    {
        $bus = $this->bus;

        return $this->dispatch($bus, new \App\Catalog\Application\Command\CreateProduct\CreateProductCommand(
            name: $name,
            description: 'Desc',
            priceAmount: 4999,
            priceCurrency: 'EUR',
            category: 'CYCLING',
            brand: 'Mizuno',
            attributes: [],
            stock: 10,
            imageUrl: null,
        ));
    }
}
