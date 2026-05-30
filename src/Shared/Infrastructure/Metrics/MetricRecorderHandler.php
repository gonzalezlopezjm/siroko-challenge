<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Event\OrderCreated;
use App\Search\Domain\Event\SearchPerformed;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

final class MetricRecorderHandler
{
    public function __construct(private readonly MetricWriter $writer) {}

    #[AsMessageHandler]
    public function onOrderCreated(OrderCreated $event): void
    {
        $this->writer->record('orders.created', 1, [
            'orderId'  => $event->orderId(),
            'currency' => $event->totalCurrency(),
        ]);
        $this->writer->record('orders.revenue', $event->totalAmount() / 100, [
            'currency' => $event->totalCurrency(),
        ]);
    }

    #[AsMessageHandler]
    public function onOrderCancelled(OrderCancelled $event): void
    {
        $this->writer->record('orders.cancelled', 1, ['orderId' => $event->orderId()]);
    }

    #[AsMessageHandler]
    public function onSearchPerformed(SearchPerformed $event): void
    {
        $this->writer->record('search.performed', 1, [
            'results'         => $event->resultsCount(),
            'keyword_fallback'=> $event->keywordFallback(),
            'empty'           => $event->resultsCount() === 0,
        ]);

        if ($event->resultsCount() === 0) {
            $this->writer->record('search.no_results', 1);
        }
    }
}
