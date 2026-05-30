<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Exception\InvalidStockException;
use App\Catalog\Domain\Model\Stock;
use PHPUnit\Framework\TestCase;

final class StockTest extends TestCase
{
    public function testCreateWithValidQuantity(): void
    {
        $stock = Stock::of(10);

        self::assertSame(10, $stock->quantity());
        self::assertTrue($stock->isAvailable());
    }

    public function testZeroQuantityIsValidButNotAvailable(): void
    {
        $stock = Stock::of(0);

        self::assertSame(0, $stock->quantity());
        self::assertFalse($stock->isAvailable());
    }

    public function testNegativeQuantityThrows(): void
    {
        $this->expectException(InvalidStockException::class);

        Stock::of(-1);
    }

    public function testDecreaseReducesQuantity(): void
    {
        $stock    = Stock::of(10);
        $newStock = $stock->decrease(3);

        self::assertSame(7, $newStock->quantity());
        self::assertSame(10, $stock->quantity()); // original unchanged (immutable)
    }

    public function testDecreaseToZeroIsAllowed(): void
    {
        $stock    = Stock::of(5);
        $newStock = $stock->decrease(5);

        self::assertSame(0, $newStock->quantity());
        self::assertFalse($newStock->isAvailable());
    }

    public function testDecreaseMoreThanAvailableThrows(): void
    {
        $this->expectException(InvalidStockException::class);

        Stock::of(2)->decrease(3);
    }
}
