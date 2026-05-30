<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Exception\InvalidPriceException;
use App\Catalog\Domain\Model\Currency;
use App\Catalog\Domain\Model\Money;
use PHPUnit\Framework\TestCase;

final class MoneyTest extends TestCase
{
    public function testCreateValidMoney(): void
    {
        $money = Money::of(1999, Currency::EUR);

        self::assertSame(1999, $money->amount());
        self::assertSame(Currency::EUR, $money->currency());
    }

    public function testRejectsZeroAmount(): void
    {
        $this->expectException(InvalidPriceException::class);

        Money::of(0, Currency::EUR);
    }

    public function testRejectsNegativeAmount(): void
    {
        $this->expectException(InvalidPriceException::class);

        Money::of(-1, Currency::EUR);
    }

    public function testEquality(): void
    {
        $a = Money::of(500, Currency::EUR);
        $b = Money::of(500, Currency::EUR);
        $c = Money::of(500, Currency::USD);

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testFromPersistanceSkipsValidation(): void
    {
        // Demonstrates that the ORM reconstitution path bypasses business rules.
        $money = Money::fromPersistence(0, Currency::EUR);

        self::assertSame(0, $money->amount());
    }
}
