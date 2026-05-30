<?php

declare(strict_types=1);

namespace App\Search\Application\Query;

final readonly class SearchResponseDTO
{
    public function __construct(
        public array $results,
        public int $resultsCount,
    ) {}
}
