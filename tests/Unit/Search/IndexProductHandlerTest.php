<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search;

use App\Catalog\Domain\Event\ProductCreated;
use App\Search\Application\EventListener\IndexProductHandler;
use App\Search\Application\Service\ProductIndexer;
use App\Search\Domain\Port\SemanticSearchPort;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class IndexProductHandlerTest extends TestCase
{
    private ProductIndexer&MockObject $indexer;
    private SemanticSearchPort&MockObject $semanticSearchPort;
    private IndexProductHandler $handler;

    protected function setUp(): void
    {
        $this->indexer            = $this->createMock(ProductIndexer::class);
        $this->semanticSearchPort = $this->createMock(SemanticSearchPort::class);

        $this->handler = new IndexProductHandler(
            indexer:             $this->indexer,
            semanticSearchPort:  $this->semanticSearchPort,
        );
    }

    public function testDelegatesIndexingToProductIndexer(): void
    {
        $this->indexer
            ->expects($this->once())
            ->method('index')
            ->with(
                productId:     '550e8400-e29b-41d4-a716-446655440000',
                name:          'Camiseta Running',
                description:   'Descripcion',
                category:      'FITNESS',
                brand:         'Nike',
                priceAmount:   4999,
                priceCurrency: 'EUR',
                stock:         10,
                attributes:    ['color' => ['negro']],
            );

        $this->handler->onProductCreated($this->makeEvent(attributes: ['color' => ['negro']]));
    }

    public function testDeleteRemovesFromIndex(): void
    {
        $this->semanticSearchPort
            ->expects($this->once())
            ->method('delete')
            ->with('550e8400-e29b-41d4-a716-446655440000');

        $this->handler->onProductDeleted(
            new \App\Catalog\Domain\Event\ProductDeleted('550e8400-e29b-41d4-a716-446655440000'),
        );
    }

    private function makeEvent(array $attributes = []): ProductCreated
    {
        return new ProductCreated(
            productId:     '550e8400-e29b-41d4-a716-446655440000',
            name:          'Camiseta Running',
            description:   'Descripcion',
            priceAmount:   4999,
            priceCurrency: 'EUR',
            category:      'FITNESS',
            brand:         'Nike',
            attributes:    $attributes,
            stock:         10,
            imageUrl:      null,
        );
    }
}
