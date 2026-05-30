<?php

declare(strict_types=1);

namespace App\Order\Application\Query;

final class OrderLineDTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $productId,
        public readonly string $productName,
        public readonly int $unitPriceAmount,
        public readonly string $unitPriceCurrency,
        public readonly int $quantity,
        public readonly int $subtotalAmount,
        public readonly string $subtotalCurrency,
    ) {}
}
