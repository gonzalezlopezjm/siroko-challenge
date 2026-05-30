<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search;

use App\Catalog\Application\Query\GetProduct\GetProductQuery;
use App\Catalog\Application\Query\ProductDTO;
use App\Search\Application\Query\SearchProducts\SearchProductsHandler;
use App\Search\Application\Query\SearchProducts\SearchProductsQuery;
use App\Search\Application\Query\SearchResponseDTO;
use App\Search\Application\Query\SearchResultDTO;
use App\Search\Domain\Model\ParsedSearchQuery;
use App\Search\Domain\Model\SearchResult;
use App\Search\Domain\Port\EmbeddingPort;
use App\Search\Domain\Port\QueryExpanderPort;
use App\Search\Domain\Port\SemanticSearchPort;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class SearchProductsHandlerTest extends TestCase
{
    private EmbeddingPort&MockObject $embeddingPort;
    private SemanticSearchPort&MockObject $semanticSearchPort;
    private QueryExpanderPort&MockObject $queryExpander;
    private MessageBusInterface&MockObject $bus;
    private CacheInterface&MockObject $cache;
    private SearchProductsHandler $handler;

    protected function setUp(): void
    {
        $this->embeddingPort      = $this->createMock(EmbeddingPort::class);
        $this->semanticSearchPort = $this->createMock(SemanticSearchPort::class);
        $this->queryExpander      = $this->createMock(QueryExpanderPort::class);

        $this->bus = $this->createMock(MessageBusInterface::class);
        $this->bus->method('dispatch')->willReturnCallback(function (object $message): Envelope {
            if ($message instanceof GetProductQuery) {
                $dto = $this->makeProductDTO($message->productId);
                return (new Envelope($message))->with(new HandledStamp($dto, 'handler'));
            }
            return new Envelope($message);
        });

        $this->cache = $this->createMock(CacheInterface::class);
        $this->cache->method('get')->willReturnCallback(
            fn(string $key, callable $fn) => $fn($this->createMock(ItemInterface::class)),
        );

        $this->handler = new SearchProductsHandler(
            embeddingPort:       $this->embeddingPort,
            semanticSearchPort:  $this->semanticSearchPort,
            queryExpander:       $this->queryExpander,
            eventBus:            $this->bus,
            similarityThreshold: 0.7,
            cache:               $this->cache,
        );
    }

    public function testQueryIsExpandedAndEmbeddedBeforeSearch(): void
    {
        $parsed = new ParsedSearchQuery('mallot mizuno maillot ciclismo camiseta técnica manga corta');
        $vector = array_fill(0, 1536, 0.0);

        $this->queryExpander
            ->expects($this->once())
            ->method('expand')
            ->with('mallot mizuno')
            ->willReturn($parsed);

        $this->embeddingPort
            ->expects($this->once())
            ->method('embed')
            ->with($parsed->expandedText)
            ->willReturn($vector);

        $searchResult = new SearchResult('product-1', 'Mallot Mizuno', 'CYCLING', 'Mizuno', 0.95);

        $this->semanticSearchPort
            ->expects($this->once())
            ->method('search')
            ->with($vector, 10, 0.7, [])
            ->willReturn([$searchResult]);

        $result = ($this->handler)(new SearchProductsQuery('mallot mizuno', 10));

        self::assertInstanceOf(SearchResponseDTO::class, $result);
        self::assertCount(1, $result->results);
        self::assertInstanceOf(SearchResultDTO::class, $result->results[0]);
        self::assertSame('product-1', $result->results[0]->product->id);
        self::assertSame(0.95, $result->results[0]->score);
    }

    public function testExtractedFiltersArePassedToSearch(): void
    {
        $parsed = new ParsedSearchQuery(
            expandedText: 'camiseta azul de mujer para running transpirable',
            colors:       ['azul'],
            brand:        null,
            genero:       ['mujer'],
            tallas:       [],
        );

        $this->queryExpander->method('expand')->willReturn($parsed);
        $this->embeddingPort->method('embed')->willReturn(array_fill(0, 1536, 0.0));

        $this->semanticSearchPort
            ->expects($this->once())
            ->method('search')
            ->with(
                $this->anything(),
                $this->anything(),
                $this->anything(),
                ['color' => ['azul'], 'genero' => ['mujer']],
            )
            ->willReturn([]);

        ($this->handler)(new SearchProductsQuery('camiseta azul de mujer', 10));
    }

    public function testReturnsEmptyWhenQdrantFindsNothing(): void
    {
        $this->queryExpander->method('expand')->willReturn(new ParsedSearchQuery('some expanded query'));
        $this->embeddingPort->method('embed')->willReturn(array_fill(0, 1536, 0.0));
        $this->semanticSearchPort->method('search')->willReturn([]);

        $result = ($this->handler)(new SearchProductsQuery('some query', 10));

        self::assertInstanceOf(SearchResponseDTO::class, $result);
        self::assertEmpty($result->results);
        self::assertSame(0, $result->resultsCount);
    }

    private function makeProductDTO(string $id): ProductDTO
    {
        return new ProductDTO(
            id:            $id,
            name:          'Mallot Mizuno',
            description:   'Mallot de ciclismo profesional',
            priceAmount:   4999,
            priceCurrency: 'EUR',
            category:      'CYCLING',
            brand:         'Mizuno',
            attributes:    [],
            stock:         10,
            imageUrl:      null,
            createdAt:     '2026-01-01T00:00:00+00:00',
            updatedAt:     '2026-01-01T00:00:00+00:00',
        );
    }
}
