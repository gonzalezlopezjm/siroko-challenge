<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart;

use App\Cart\Domain\Model\CartItem;
use PHPUnit\Framework\TestCase;

final class CartItemTest extends TestCase
{
    private function item(int $price = 1000, int $quantity = 2): CartItem
    {
        return new CartItem('prod-1', 'Camiseta', $price, 'EUR', $quantity);
    }

    public function testSubtotalAmountIsQuantityTimesUnitPrice(): void
    {
        self::assertSame(2000, $this->item(1000, 2)->subtotalAmount());
    }

    public function testWithQuantityReturnsNewImmutableInstance(): void
    {
        $original = $this->item(1000, 2);
        $updated  = $original->withQuantity(5);

        self::assertNotSame($original, $updated);
        self::assertSame(2, $original->quantity());
        self::assertSame(5, $updated->quantity());
        self::assertSame('prod-1', $updated->productId());
    }

    public function testToArrayAndFromArrayRoundtrip(): void
    {
        $item  = new CartItem('prod-abc', 'Guantes', 2500, 'EUR', 3);
        $array = $item->toArray();

        self::assertSame('prod-abc', $array['productId']);
        self::assertSame('Guantes', $array['productName']);
        self::assertSame(2500, $array['unitPriceAmount']);
        self::assertSame('EUR', $array['unitPriceCurrency']);
        self::assertSame(3, $array['quantity']);

        $restored = CartItem::fromArray($array);
        self::assertSame($item->subtotalAmount(), $restored->subtotalAmount());
        self::assertSame($item->productId(), $restored->productId());
    }
}
