<?php

declare(strict_types=1);

namespace App\Order\Application\Query;

final class OrderDTO
{
    /**
     * @param OrderLineDTO[] $lines
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $customerId,
        public readonly array $lines,
        public readonly int $totalAmount,
        public readonly string $totalCurrency,
        public readonly string $status,
        public readonly string $shippingStreet,
        public readonly string $shippingCity,
        public readonly string $shippingPostalCode,
        public readonly string $shippingCountry,
        public readonly string $createdAt,
    ) {}
}
