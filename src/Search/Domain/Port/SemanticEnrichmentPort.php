<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface SemanticEnrichmentPort
{
    public function enrich(
        string $name,
        string $description,
        string $category,
        string $brand,
        int $priceAmount,
        string $priceCurrency,
        array $attributes,
    ): string;
}
