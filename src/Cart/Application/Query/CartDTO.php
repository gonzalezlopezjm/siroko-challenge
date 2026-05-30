<?php

declare(strict_types=1);

namespace App\Cart\Application\Query;

final class CartDTO
{
    /**
     * @param CartItemDTO[] $items
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $customerId,
        public readonly array $items,
        public readonly int $totalAmount,
        public readonly string $totalCurrency,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}
}
