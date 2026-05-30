<?php

declare(strict_types=1);

namespace App\Tests\Unit\Cart;

use App\Cart\Domain\Model\CartId;
use PHPUnit\Framework\TestCase;

final class CartIdTest extends TestCase
{
    public function testGenerateReturnsValidUuid(): void
    {
        $id = CartId::generate();
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->value(),
        );
    }

    public function testFromStringAcceptsValidUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id   = CartId::fromString($uuid);
        self::assertSame($uuid, $id->value());
    }

    public function testFromStringThrowsOnInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CartId::fromString('not-a-uuid');
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        self::assertTrue(CartId::fromString($uuid)->equals(CartId::fromString($uuid)));
    }

    public function testEqualsReturnsFalseForDifferentValue(): void
    {
        $a = CartId::generate();
        $b = CartId::generate();
        self::assertFalse($a->equals($b));
    }
}
