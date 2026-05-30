<?php

declare(strict_types=1);

namespace App\Cart\Application\Query;

final class CartItemDTO
{
    public function __construct(
        public readonly string $productId,
        public readonly string $productName,
        public readonly int $unitPriceAmount,
        public readonly string $unitPriceCurrency,
        public readonly int $quantity,
        public readonly int $subtotalAmount,
        public readonly string $subtotalCurrency,
    ) {}
}
