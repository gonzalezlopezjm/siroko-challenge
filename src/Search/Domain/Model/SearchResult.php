<?php

declare(strict_types=1);

namespace App\Search\Domain\Model;

final readonly class SearchResult
{
    public function __construct(
        public string $productId,
        public string $name,
        public string $category,
        public string $brand,
        public ?float $score = null,
    ) {}
}
