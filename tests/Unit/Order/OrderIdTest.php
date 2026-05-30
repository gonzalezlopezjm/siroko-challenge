<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order;

use App\Order\Domain\Model\OrderId;
use PHPUnit\Framework\TestCase;

final class OrderIdTest extends TestCase
{
    public function testGenerateReturnsValidUuid(): void
    {
        $id = OrderId::generate();
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id->value(),
        );
    }

    public function testFromStringAcceptsValidUuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';
        self::assertSame($uuid, OrderId::fromString($uuid)->value());
    }

    public function testFromStringThrowsOnInvalidUuid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        OrderId::fromString('invalid');
    }
}
