<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateProduct;

use App\Shared\Domain\Bus\Command\CommandInterface;

final class UpdateProductCommand implements CommandInterface
{
    /**
     * @param array<string, string[]>|null $attributes
     */
    public function __construct(
        public readonly string $productId,
        public readonly ?string $name,
        public readonly ?string $description,
        public readonly ?int $priceAmount,
        public readonly ?string $priceCurrency,
        public readonly ?string $category,
        public readonly ?string $brand,
        public readonly ?array $attributes,
        public readonly ?int $stock,
        public readonly ?string $imageUrl,
        public readonly bool $clearImageUrl = false,
    ) {}
}
