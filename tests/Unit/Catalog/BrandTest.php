<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Model\Brand;
use PHPUnit\Framework\TestCase;

final class BrandTest extends TestCase
{
    public function testCreatesFromValidString(): void
    {
        $brand = Brand::fromString('Siroko');

        self::assertSame('Siroko', $brand->value());
    }

    public function testTrimsLeadingAndTrailingWhitespace(): void
    {
        $brand = Brand::fromString('  Siroko  ');

        self::assertSame('Siroko', $brand->value());
    }

    public function testRejectsEmptyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Brand::fromString('');
    }

    public function testRejectsWhitespaceOnlyString(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Brand::fromString('   ');
    }

    public function testRejectsStringExceeding100Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Brand::fromString(str_repeat('a', 101));
    }

    public function testAcceptsStringOfExactly100Characters(): void
    {
        $brand = Brand::fromString(str_repeat('a', 100));

        self::assertSame(100, mb_strlen($brand->value()));
    }

    public function testToString(): void
    {
        $brand = Brand::fromString('Siroko');

        self::assertSame('Siroko', (string) $brand);
    }
}
