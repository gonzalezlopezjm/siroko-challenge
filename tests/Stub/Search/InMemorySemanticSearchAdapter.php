<?php

declare(strict_types=1);

namespace Tests\Stub\Search;

use App\Search\Domain\Model\SearchResult;
use App\Search\Domain\Port\SemanticSearchPort;

final class InMemorySemanticSearchAdapter implements SemanticSearchPort
{
    public array $store = [];

    public function searchByText(string $query, int $limit, array $filters = []): array
    {
        return $this->search([], $limit, 0.0, $filters);
    }

    public function search(array $queryVector, int $limit, float $threshold, array $filters = []): array
    {
        $results = [];

        foreach ($this->store as $productId => $payload) {
            $results[] = new SearchResult(
                productId: $productId,
                name:      $payload['name'] ?? '',
                category:  $payload['category'] ?? '',
                brand:     $payload['brand'] ?? '',
                score:     1.0,
            );
        }

        return array_slice($results, 0, $limit);
    }

    public function upsert(string $productId, array $vector, array $payload): void
    {
        $this->store[$productId] = $payload;
    }

    public function delete(string $productId): void
    {
        unset($this->store[$productId]);
    }

    public function clear(): void
    {
        $this->store = [];
    }
}
