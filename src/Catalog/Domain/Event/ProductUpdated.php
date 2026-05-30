<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;

final class ProductUpdated implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly string $productId,
        private readonly string $name,
        private readonly string $description,
        private readonly int $priceAmount,
        private readonly string $priceCurrency,
        private readonly string $category,
        private readonly string $brand,
        private readonly array $attributes,
        private readonly int $stock,
        private readonly ?string $imageUrl,
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
    public function name(): string { return $this->name; }
    public function description(): string { return $this->description; }
    public function priceAmount(): int { return $this->priceAmount; }
    public function priceCurrency(): string { return $this->priceCurrency; }
    public function category(): string { return $this->category; }
    public function brand(): string { return $this->brand; }
    public function attributes(): array { return $this->attributes; }
    public function stock(): int { return $this->stock; }
    public function imageUrl(): ?string { return $this->imageUrl; }
}
