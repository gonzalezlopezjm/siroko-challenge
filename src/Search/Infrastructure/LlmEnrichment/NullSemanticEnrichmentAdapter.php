<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\LlmEnrichment;

use App\Search\Domain\Port\SemanticEnrichmentPort;

final class NullSemanticEnrichmentAdapter implements SemanticEnrichmentPort
{
    public function enrich(
        string $name,
        string $description,
        string $category,
        string $brand,
        int $priceAmount,
        string $priceCurrency,
        array $attributes,
    ): string {
        return implode('. ', array_filter([$name, $brand, $category, $description]));
    }
}
