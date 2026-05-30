<?php

declare(strict_types=1);

namespace App\Search\Application\EventListener;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductDeleted;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Search\Application\Service\ProductIndexer;
use App\Search\Domain\Port\SemanticSearchPort;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class IndexProductHandler
{
    public function __construct(
        private readonly ProductIndexer $indexer,
        private readonly SemanticSearchPort $semanticSearchPort,
    ) {}

    #[AsMessageHandler]
    public function onProductCreated(ProductCreated $event): void
    {
        $this->indexOne($event);
    }

    #[AsMessageHandler]
    public function onProductUpdated(ProductUpdated $event): void
    {
        $this->indexOne($event);
    }

    #[AsMessageHandler]
    public function onProductDeleted(ProductDeleted $event): void
    {
        $this->semanticSearchPort->delete($event->productId());
    }

    private function indexOne(ProductCreated|ProductUpdated $event): void
    {
        $this->indexer->index(
            productId:     $event->productId(),
            name:          $event->name(),
            description:   $event->description(),
            category:      $event->category(),
            brand:         $event->brand(),
            priceAmount:   $event->priceAmount(),
            priceCurrency: $event->priceCurrency(),
            stock:         $event->stock(),
            attributes:    $event->attributes(),
        );
    }
}
