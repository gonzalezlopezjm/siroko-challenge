<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;

final class ProductDeleted implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(private readonly string $productId)
    {
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

    public function productId(): string
    {
        return $this->productId;
    }
}
