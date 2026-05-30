<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

final class ProductDTO
{
    /**
     * @param array<string, string[]> $attributes
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $description,
        public readonly int $priceAmount,
        public readonly string $priceCurrency,
        public readonly string $category,
        public readonly string $brand,
        public readonly array $attributes,
        public readonly int $stock,
        public readonly ?string $imageUrl,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {}
}
