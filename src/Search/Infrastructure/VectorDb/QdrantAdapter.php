<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\VectorDb;

use App\Search\Domain\Model\SearchResult;
use App\Search\Domain\Port\SemanticSearchPort;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class QdrantAdapter implements SemanticSearchPort
{
    private const VECTOR_SIZE = 1536;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $collection,
    ) {}

    public function search(array $queryVector, int $limit, float $threshold, array $filters = []): array
    {
        $must = [
            ['key' => 'in_stock', 'match' => ['value' => true]],
        ];

        if (!empty($filters['color'])) {
            $must[] = ['key' => 'color', 'match' => ['any' => array_values($filters['color'])]];
        }
        if (!empty($filters['brand'])) {
            $must[] = ['key' => 'brand', 'match' => ['value' => $filters['brand']]];
        }
        if (!empty($filters['genero'])) {
            $must[] = ['key' => 'genero', 'match' => ['any' => array_values($filters['genero'])]];
        }
        if (!empty($filters['talla'])) {
            $must[] = ['key' => 'talla', 'match' => ['any' => array_values($filters['talla'])]];
        }
        if (!empty($filters['temporada'])) {
            $temporada = array_values($filters['temporada']);
            // Expand "invierno" to also match "invierno suave" (mild winter products)
            if (in_array('invierno', $temporada, true)) {
                $temporada[] = 'invierno suave';
            }
            $must[] = ['key' => 'temporada', 'match' => ['any' => array_unique($temporada)]];
        }
        if (!empty($filters['estilo'])) {
            $must[] = ['key' => 'estilo', 'match' => ['any' => array_values($filters['estilo'])]];
        }
        if (!empty($filters['uso'])) {
            $must[] = ['key' => 'uso', 'match' => ['any' => array_values($filters['uso'])]];
        }
        if (!empty($filters['ajuste'])) {
            $must[] = ['key' => 'ajuste', 'match' => ['any' => array_values($filters['ajuste'])]];
        }

        $response = $this->httpClient->request('POST', "/collections/{$this->collection}/points/search", [
            'json' => [
                'vector'          => $queryVector,
                'limit'           => $limit,
                'score_threshold' => $threshold,
                'with_payload'    => true,
                'filter'          => ['must' => $must],
            ],
        ]);

        if ($response->getStatusCode() === 404) {
            return [];
        }

        $data = $response->toArray();

        return array_map(
            fn(array $hit) => new SearchResult(
                productId: (string) $hit['id'],
                name:      $hit['payload']['name'] ?? '',
                category:  $hit['payload']['category'] ?? '',
                brand:     $hit['payload']['brand'] ?? '',
                score:     (float) $hit['score'],
            ),
            $data['result'] ?? [],
        );
    }

    public function searchByText(string $query, int $limit, array $filters = []): array
    {
        $searchText = $this->stripStopWords($query);

        if ($searchText === '') {
            return [];
        }

        $must = [
            ['key' => 'in_stock', 'match' => ['value' => true]],
            ['key' => 'name', 'match' => ['text' => $searchText]],
        ];

        if (!empty($filters['color'])) {
            $must[] = ['key' => 'color', 'match' => ['any' => array_values($filters['color'])]];
        }
        if (!empty($filters['brand'])) {
            $must[] = ['key' => 'brand', 'match' => ['value' => $filters['brand']]];
        }
        if (!empty($filters['genero'])) {
            $must[] = ['key' => 'genero', 'match' => ['any' => array_values($filters['genero'])]];
        }

        $response = $this->httpClient->request('POST', "/collections/{$this->collection}/points/scroll", [
            'json' => [
                'filter'       => ['must' => $must],
                'limit'        => $limit,
                'with_payload' => true,
                'with_vector'  => false,
            ],
        ]);

        if ($response->getStatusCode() === 404) {
            return [];
        }

        return array_map(
            fn(array $point) => new SearchResult(
                productId: (string) $point['id'],
                name:      $point['payload']['name'] ?? '',
                category:  $point['payload']['category'] ?? '',
                brand:     $point['payload']['brand'] ?? '',
                score:     null,
            ),
            $response->toArray()['result']['points'] ?? [],
        );
    }

    public function upsert(string $productId, array $vector, array $payload): void
    {
        $this->ensureCollectionExists();

        $this->httpClient->request('PUT', "/collections/{$this->collection}/points", [
            'json' => [
                'points' => [[
                    'id'      => $productId,
                    'vector'  => $vector,
                    'payload' => $payload,
                ]],
            ],
        ])->getContent();
    }

    public function delete(string $productId): void
    {
        $this->httpClient->request('POST', "/collections/{$this->collection}/points/delete", [
            'json' => ['points' => [$productId]],
        ])->getContent();
    }

    public function clear(): void
    {
        $response = $this->httpClient->request('GET', "/collections/{$this->collection}");

        if ($response->getStatusCode() === 404) {
            return;
        }

        $this->httpClient->request('DELETE', "/collections/{$this->collection}")->getContent();
    }

    private function stripStopWords(string $query): string
    {
        static $stopWords = [
            'de', 'del', 'la', 'el', 'los', 'las', 'un', 'una', 'unos', 'unas',
            'para', 'con', 'por', 'en', 'y', 'a', 'al', 'que', 'se', 'su', 'sus',
            'me', 'te', 'le', 'nos', 'les', 'mi', 'mis', 'tu', 'tus',
            'the', 'a', 'an', 'for', 'and', 'of', 'to', 'in', 'on', 'with',
        ];

        $words = preg_split('/\s+/', mb_strtolower(trim($query)));
        $significant = array_filter($words, fn($w) => strlen($w) > 2 && !in_array($w, $stopWords, true));

        return implode(' ', $significant);
    }

    private function ensureCollectionExists(): void
    {
        $response = $this->httpClient->request('GET', "/collections/{$this->collection}");

        if ($response->getStatusCode() === 404) {
            $this->httpClient->request('PUT', "/collections/{$this->collection}", [
                'json' => ['vectors' => ['size' => self::VECTOR_SIZE, 'distance' => 'Cosine']],
            ])->getContent();
        }

        $this->httpClient->request('PUT', "/collections/{$this->collection}/index", [
            'json' => ['field_name' => 'name', 'field_schema' => 'text'],
        ])->getContent();
    }
}
