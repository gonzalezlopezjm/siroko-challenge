<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Model\ProductDescription;
use PHPUnit\Framework\TestCase;

final class ProductDescriptionTest extends TestCase
{
    public function testCreatesFromValidString(): void
    {
        $description = ProductDescription::fromString('Maillot aerodinámico para ciclismo de carretera.');

        self::assertSame('Maillot aerodinámico para ciclismo de carretera.', $description->value());
    }

    public function testAllowsEmptyString(): void
    {
        $description = ProductDescription::fromString('');

        self::assertSame('', $description->value());
    }

    public function testEmptyFactoryReturnsEmptyDescription(): void
    {
        $description = ProductDescription::empty();

        self::assertSame('', $description->value());
    }

    public function testRejectsStringExceeding2000Characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProductDescription::fromString(str_repeat('a', 2001));
    }

    public function testAcceptsStringOfExactly2000Characters(): void
    {
        $description = ProductDescription::fromString(str_repeat('a', 2000));

        self::assertSame(2000, mb_strlen($description->value()));
    }
}
