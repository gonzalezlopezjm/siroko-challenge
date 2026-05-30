<?php

declare(strict_types=1);

namespace App\Search\Application\Query;

use App\Catalog\Application\Query\ProductDTO;

final readonly class SearchResultDTO
{
    public function __construct(
        public ProductDTO $product,
        public ?float $score = null,
    ) {}
}
