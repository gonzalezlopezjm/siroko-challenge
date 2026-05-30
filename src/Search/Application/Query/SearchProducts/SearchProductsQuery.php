<?php

declare(strict_types=1);

namespace App\Search\Application\Query\SearchProducts;

use App\Shared\Domain\Bus\Query\QueryInterface;

final class SearchProductsQuery implements QueryInterface
{
    public function __construct(
        public readonly string $query,
        public readonly int $limit = 10,
    ) {}
}
