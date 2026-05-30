<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\LlmEnrichment;

use App\Search\Domain\Port\SemanticEnrichmentPort;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SemanticEnrichmentAdapterFactory implements SemanticEnrichmentPort
{
    private readonly SemanticEnrichmentPort $inner;

    public function __construct(
        HttpClientInterface $httpClient,
        string $apiKey,
        string $modelName,
    ) {
        $this->inner = $apiKey === ''
            ? new NullSemanticEnrichmentAdapter()
            : new OpenAiSemanticEnrichmentAdapter($httpClient, $modelName);
    }

    public function enrich(
        string $name,
        string $description,
        string $category,
        string $brand,
        int $priceAmount,
        string $priceCurrency,
        array $attributes,
    ): string {
        return $this->inner->enrich($name, $description, $category, $brand, $priceAmount, $priceCurrency, $attributes);
    }
}
