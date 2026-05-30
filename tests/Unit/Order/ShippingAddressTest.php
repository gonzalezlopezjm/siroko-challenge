<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order;

use App\Order\Domain\Model\ShippingAddress;
use PHPUnit\Framework\TestCase;

final class ShippingAddressTest extends TestCase
{
    public function testOfCreatesAddressWithAllFields(): void
    {
        $address = ShippingAddress::of('Calle Mayor 1', 'Madrid', '28001', 'ES');

        self::assertSame('Calle Mayor 1', $address->street());
        self::assertSame('Madrid', $address->city());
        self::assertSame('28001', $address->postalCode());
        self::assertSame('ES', $address->country());
    }

    /** @dataProvider emptyFieldProvider */
    public function testOfThrowsWhenAnyFieldIsEmpty(
        string $street,
        string $city,
        string $postalCode,
        string $country,
    ): void {
        $this->expectException(\InvalidArgumentException::class);
        ShippingAddress::of($street, $city, $postalCode, $country);
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function emptyFieldProvider(): array
    {
        return [
            'empty street'     => ['', 'Madrid', '28001', 'ES'],
            'empty city'       => ['Calle 1', '', '28001', 'ES'],
            'empty postalCode' => ['Calle 1', 'Madrid', '', 'ES'],
            'empty country'    => ['Calle 1', 'Madrid', '28001', ''],
        ];
    }
}
