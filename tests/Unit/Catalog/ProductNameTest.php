<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Model\ProductName;
use PHPUnit\Framework\TestCase;

final class ProductNameTest extends TestCase
{
    public function testCreatesFromValidString(): void
    {
        $name = ProductName::fromString('Maillot Aero');

        self::assertSame('Maillot Aero', $name->value());
    }

    public function testTrimsLeadingAndTrailingWhitespace(): void
    {
        $name = ProductName::fromString('  Culote Pro  ');

        self::assertSame('Culote Pro', $name->value());
    }

    public function testRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProductName::fromString('');
    }

    public function testRejectsWhitespaceOnlyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProductName::fromString('   ');
    }

    public function testRejectsStringExceeding255Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProductName::fromString(str_repeat('a', 256));
    }

    public function testAcceptsStringOfExactly255Characters(): void
    {
        $name = ProductName::fromString(str_repeat('a', 255));

        self::assertSame(255, mb_strlen($name->value()));
    }

    public function testEquality(): void
    {
        $a = ProductName::fromString('Maillot Aero');
        $b = ProductName::fromString('Maillot Aero');
        $c = ProductName::fromString('Culote Pro');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testToString(): void
    {
        $name = ProductName::fromString('Maillot Aero');

        self::assertSame('Maillot Aero', (string) $name);
    }
}
