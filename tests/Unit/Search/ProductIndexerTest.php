<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search;

use App\Search\Application\Service\ProductIndexer;
use App\Search\Application\Service\ProductPayloadBuilder;
use App\Search\Domain\Port\EmbeddingPort;
use App\Search\Domain\Port\SemanticEnrichmentPort;
use App\Search\Domain\Port\SemanticSearchPort;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ProductIndexerTest extends TestCase
{
    private EmbeddingPort&MockObject $embeddingPort;
    private SemanticSearchPort&MockObject $semanticSearchPort;
    private SemanticEnrichmentPort&MockObject $enrichmentPort;
    private ProductIndexer $indexer;

    protected function setUp(): void
    {
        $this->embeddingPort      = $this->createMock(EmbeddingPort::class);
        $this->semanticSearchPort = $this->createMock(SemanticSearchPort::class);
        $this->enrichmentPort     = $this->createMock(SemanticEnrichmentPort::class);

        $this->indexer = new ProductIndexer(
            embeddingPort:      $this->embeddingPort,
            semanticSearchPort: $this->semanticSearchPort,
            payloadBuilder:     new ProductPayloadBuilder(),
            enrichmentPort:     $this->enrichmentPort,
        );
    }

    public function testEnrichmentTextIsEmbeddedAndStoredInPayload(): void
    {
        $enrichedText = 'Camiseta técnica de running para entrenamiento de alta intensidad...';

        $this->enrichmentPort
            ->expects($this->once())
            ->method('enrich')
            ->with('Camiseta Running', 'Descripcion', 'CYCLING', 'Nike', 4999, 'EUR', [])
            ->willReturn($enrichedText);

        $this->embeddingPort
            ->expects($this->once())
            ->method('embed')
            ->with($enrichedText)
            ->willReturn(array_fill(0, 1536, 0.1));

        $capturedPayload = [];
        $this->semanticSearchPort
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (string $_id, array $_v, array $payload) use (&$capturedPayload): void {
                $capturedPayload = $payload;
            });

        $this->indexer->index('pid-1', 'Camiseta Running', 'Descripcion', 'CYCLING', 'Nike', 4999, 'EUR', 10, []);

        self::assertSame($enrichedText, $capturedPayload['enriched_text']);
        self::assertSame('Camiseta Running', $capturedPayload['name']);
        self::assertSame('Nike', $capturedPayload['brand']);
    }

    public function testRawAttributesAreStoredInPayload(): void
    {
        $this->enrichmentPort->method('enrich')->willReturn('');
        $this->embeddingPort->method('embed')->willReturn(array_fill(0, 1536, 0.1));

        $capturedPayload = [];
        $this->semanticSearchPort->method('upsert')
            ->willReturnCallback(function (string $_id, array $_v, array $payload) use (&$capturedPayload): void {
                $capturedPayload = $payload;
            });

        $this->indexer->index('pid-1', 'Test', '', 'FITNESS', 'Nike', 4999, 'EUR', 10, [
            'color'  => ['negro', 'blanco'],
            'tallas' => ['S', 'M', 'L'],
        ]);

        self::assertSame(['negro', 'blanco'], $capturedPayload['color']);
        self::assertSame(['S', 'M', 'L'], $capturedPayload['tallas']);
    }

    public function testIndexingProceedsWithEmptyEnrichment(): void
    {
        $this->enrichmentPort->method('enrich')->willReturn('');
        $this->embeddingPort->expects($this->once())->method('embed')->willReturn(array_fill(0, 1536, 0.1));
        $this->semanticSearchPort->expects($this->once())->method('upsert');

        $this->indexer->index('pid-1', 'Test', '', 'FITNESS', 'Nike', 4999, 'EUR', 10, []);
    }
}
