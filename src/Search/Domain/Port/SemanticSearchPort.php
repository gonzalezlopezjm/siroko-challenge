<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

use App\Search\Domain\Model\SearchResult;

interface SemanticSearchPort
{
    /**
     * @param  float[] $queryVector
     * @param  array<string, mixed> $filters
     * @return SearchResult[]
     */
    public function search(array $queryVector, int $limit, float $threshold, array $filters = []): array;

    /**
     * Full-text keyword search on the product name field.
     * Used as fallback when semantic search returns no results.
     *
     * @param  array<string, mixed> $filters
     * @return SearchResult[]
     */
    public function searchByText(string $query, int $limit, array $filters = []): array;

    /** @param float[] $vector */
    public function upsert(string $productId, array $vector, array $payload): void;

    public function delete(string $productId): void;

    public function clear(): void;
}
