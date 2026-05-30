<?php

declare(strict_types=1);

namespace App\Search\Application\Service;

use App\Search\Domain\Port\EmbeddingPort;
use App\Search\Domain\Port\SemanticEnrichmentPort;
use App\Search\Domain\Port\SemanticSearchPort;

class ProductIndexer
{
    public function __construct(
        private readonly EmbeddingPort $embeddingPort,
        private readonly SemanticSearchPort $semanticSearchPort,
        private readonly ProductPayloadBuilder $payloadBuilder,
        private readonly SemanticEnrichmentPort $enrichmentPort,
    ) {}

    public function index(
        string $productId,
        string $name,
        string $description,
        string $category,
        string $brand,
        int $priceAmount,
        string $priceCurrency,
        int $stock,
        array $attributes,
    ): void {
        $enrichedText = $this->enrichmentPort->enrich(
            $name, $description, $category, $brand, $priceAmount, $priceCurrency, $attributes,
        );

        $vector  = $this->embeddingPort->embed($enrichedText);
        $payload = $this->payloadBuilder->build(
            $name, $category, $brand, $priceAmount, $priceCurrency, $stock, $attributes, $enrichedText,
        );

        $this->semanticSearchPort->upsert($productId, $vector, $payload);
    }
}
