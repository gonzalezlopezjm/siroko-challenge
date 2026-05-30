<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\ListProducts;

use App\Shared\Domain\Bus\Query\QueryInterface;

final class ListProductsQuery implements QueryInterface
{
    public function __construct(
        public readonly ?string $category,
        public readonly ?string $brand,
        public readonly int $page,
        public readonly int $pageSize,
    ) {}
}
