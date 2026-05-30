<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Model\ProductAttributes;
use PHPUnit\Framework\TestCase;

final class ProductAttributesTest extends TestCase
{
    public function testCreatesFromArray(): void
    {
        $data       = ['color' => ['rojo', 'azul'], 'talla' => ['M', 'L', 'XL']];
        $attributes = ProductAttributes::fromArray($data);

        self::assertSame($data, $attributes->toArray());
    }

    public function testEmptyFactoryReturnsEmptyAttributes(): void
    {
        $attributes = ProductAttributes::empty();

        self::assertSame([], $attributes->toArray());
        self::assertTrue($attributes->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenAttributesExist(): void
    {
        $attributes = ProductAttributes::fromArray(['color' => ['negro']]);

        self::assertFalse($attributes->isEmpty());
    }

    public function testFromArrayWithEmptyArrayIsEmpty(): void
    {
        $attributes = ProductAttributes::fromArray([]);

        self::assertTrue($attributes->isEmpty());
    }

    public function testToArrayReturnsOriginalData(): void
    {
        $data       = ['temporada' => ['verano'], 'genero' => ['hombre', 'mujer']];
        $attributes = ProductAttributes::fromArray($data);

        self::assertSame($data, $attributes->toArray());
    }
}
