<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart;

use App\Cart\Domain\Exception\CartLineItemLimitExceededException;
use App\Cart\Domain\Exception\ItemNotInCartException;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use PHPUnit\Framework\TestCase;

final class CartTest extends TestCase
{
    private function newCart(?string $customerId = 'customer-1'): Cart
    {
        return Cart::create(CartId::generate(), $customerId);
    }

    private function addItem(Cart $cart, string $productId = 'prod-1', int $price = 1000, int $quantity = 1): void
    {
        $cart->addItem($productId, 'Producto', $price, 'EUR', $quantity);
    }

    public function testNewCartIsEmpty(): void
    {
        $cart = $this->newCart();
        self::assertTrue($cart->isEmpty());
        self::assertCount(0, $cart->items());
    }

    public function testAddItemIncreasesItemCount(): void
    {
        $cart = $this->newCart();
        $this->addItem($cart, 'prod-1');
        $this->addItem($cart, 'prod-2');

        self::assertFalse($cart->isEmpty());
        self::assertCount(2, $cart->items());
    }

    public function testAddSameProductIncreasesQuantity(): void
    {
        $cart = $this->newCart();
        $this->addItem($cart, 'prod-1', 1000, 3);
        $this->addItem($cart, 'prod-1', 1000, 2);

        self::assertCount(1, $cart->items());
        self::assertSame(5, $cart->items()[0]->quantity());
    }

    public function testAddItemCapsQuantityAt99(): void
    {
        $cart = $this->newCart();
        $this->addItem($cart, 'prod-1', 1000, 90);
        $this->addItem($cart, 'prod-1', 1000, 20); // 90+20=110, debe quedar en 99

        self::assertSame(99, $cart->items()[0]->quantity());
    }

    public function testAddItemThrowsWhenMax50LinesReached(): void
    {
        $cart = $this->newCart();
        for ($i = 0; $i < 50; ++$i) {
            $this->addItem($cart, "prod-{$i}");
        }

        $this->expectException(CartLineItemLimitExceededException::class);
        $this->addItem($cart, 'prod-extra');
    }

    public function testUpdateItemQuantityChangesQuantity(): void
    {
        $cart = $this->newCart();
        $this->addItem($cart, 'prod-1', 1000, 3);
        $cart->updateItemQuantity('prod-1', 7);

        self::assertSame(7, $cart->items()[0]->quantity());
    }

    public function testUpdateItemQuantityCapsAt99(): void
    {
        $cart = $this->newCart();
        $this->addItem($cart, 'prod-1');
        $cart->updateItemQuantity('prod-1', 200);

        self::assertSame(99, $cart->items()[0]->quantity());
    }

    public function testUpdateItemQuantityThrowsWhenProductNotInCart(): void
    {
        $cart = $this->newCart();

        $this->expectException(ItemNotInCartException::class);
        $cart->updateItemQuantity('prod-inexistente', 5);
    }

    public function testRemoveItemRemovesCorrectItem(): void
    {
        $cart = $this->newCart();
        $this->addItem($cart, 'prod-1');
        $this->addItem($cart, 'prod-2');
        $cart->removeItem('prod-1');

        self::assertCount(1, $cart->items());
        self::assertSame('prod-2', $cart->items()[0]->productId());
    }

    public function testRemoveItemThrowsWhenNotFound(): void
    {
        $cart = $this->newCart();

        $this->expectException(ItemNotInCartException::class);
        $cart->removeItem('prod-inexistente');
    }

    public function testClearEmptiesCart(): void
    {
        $cart = $this->newCart();
        $this->addItem($cart, 'prod-1');
        $this->addItem($cart, 'prod-2');
        $cart->clear();

        self::assertTrue($cart->isEmpty());
    }

    public function testTotalAmountSumsAllSubtotals(): void
    {
        $cart = $this->newCart();
        $cart->addItem('prod-1', 'A', 1000, 'EUR', 2); // 2000
        $cart->addItem('prod-2', 'B', 500, 'EUR', 3);  // 1500

        self::assertSame(3500, $cart->totalAmount());
    }

    public function testTotalCurrencyDefaultsToEurWhenEmpty(): void
    {
        self::assertSame('EUR', $this->newCart()->totalCurrency());
    }

    public function testTotalCurrencyUsesFirstItemCurrency(): void
    {
        $cart = $this->newCart();
        $cart->addItem('prod-1', 'A', 1000, 'EUR', 1);

        self::assertSame('EUR', $cart->totalCurrency());
    }

    public function testCustomerIdCanBeNull(): void
    {
        $cart = $this->newCart(null);
        self::assertNull($cart->customerId());
    }

    public function testUpdatedAtChangesAfterMutation(): void
    {
        $cart    = $this->newCart();
        $before  = $cart->updatedAt();
        usleep(1000); // 1 ms
        $this->addItem($cart);

        self::assertGreaterThanOrEqual($before, $cart->updatedAt());
    }
}
