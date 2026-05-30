<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Model\ProductId;
use PHPUnit\Framework\TestCase;

final class ProductIdTest extends TestCase
{
    public function testGenerateCreatesValidUuid(): void
    {
        $id = ProductId::generate();

        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->value(),
        );
    }

    public function testFromStringWithValidUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        $id   = ProductId::fromString($uuid);

        self::assertSame($uuid, $id->value());
        self::assertSame($uuid, (string) $id);
    }

    public function testFromStringWithInvalidUuidThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ProductId::fromString('not-a-uuid');
    }

    public function testEquality(): void
    {
        $a = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $b = ProductId::fromString('550e8400-e29b-41d4-a716-446655440000');
        $c = ProductId::generate();

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }
}
