<?php

declare(strict_types=1);

namespace App\Search\Application\Query\SearchProducts;

use App\Catalog\Application\Query\GetProduct\GetProductQuery;
use App\Catalog\Application\Query\ProductDTO;
use App\Search\Application\Query\SearchResponseDTO;
use App\Search\Application\Query\SearchResultDTO;
use App\Search\Domain\Event\SearchPerformed;
use App\Search\Domain\Model\SearchResult;
use App\Search\Domain\Port\EmbeddingPort;
use App\Search\Domain\Port\QueryExpanderPort;
use App\Search\Domain\Port\SemanticSearchPort;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[AsMessageHandler]
final class SearchProductsHandler
{
    public function __construct(
        private readonly EmbeddingPort $embeddingPort,
        private readonly SemanticSearchPort $semanticSearchPort,
        private readonly QueryExpanderPort $queryExpander,
        private readonly MessageBusInterface $eventBus,
        private readonly float $similarityThreshold,
        #[Autowire(service: 'cache.search')]
        private readonly CacheInterface $cache,
    ) {}

    public function __invoke(SearchProductsQuery $query): SearchResponseDTO
    {
        $cacheKey = 'search.' . md5($query->query . '|' . $query->limit);

        $response = $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($query): SearchResponseDTO {
                $item->expiresAfter(120);

                $parsed  = $this->queryExpander->expand($query->query);
                $vector  = $this->embeddingPort->embed($parsed->expandedText);
                $results = $this->semanticSearchPort->search(
                    $vector, $query->limit, $this->similarityThreshold, $parsed->toFilterArray(),
                );

                $keywordFallback = false;
                if (empty($results)) {
                    $results         = $this->semanticSearchPort->searchByText(
                        $query->query, $query->limit, $parsed->toFilterArray(),
                    );
                    $keywordFallback = !empty($results);
                }

                $enriched = [];
                foreach ($results as $r) {
                    $productDto = $this->fetchProduct($r->productId);
                    if ($productDto !== null) {
                        $enriched[] = new SearchResultDTO($productDto, $r->score);
                    }
                }

                $this->eventBus->dispatch(new SearchPerformed(
                    query: $query->query,
                    resultsCount: count($enriched),
                    keywordFallback: $keywordFallback,
                ));

                return new SearchResponseDTO(
                    results: $enriched,
                    resultsCount: count($enriched),
                );
            },
        );

        return $response;
    }

    private function fetchProduct(string $productId): ?ProductDTO
    {
        try {
            $envelope = $this->eventBus->dispatch(new GetProductQuery($productId));
            $result   = $envelope->last(HandledStamp::class)?->getResult();

            return $result instanceof ProductDTO ? $result : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
