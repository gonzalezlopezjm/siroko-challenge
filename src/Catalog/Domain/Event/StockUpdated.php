<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;

final class StockUpdated implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly string $productId,
        private readonly int $previousStock,
        private readonly int $newStock,
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function aggregateId(): string
    {
        return $this->productId;
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function productId(): string { return $this->productId; }
    public function previousStock(): int { return $this->previousStock; }
    public function newStock(): int { return $this->newStock; }
}
