<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductDeleted;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Event\StockUpdated;
use App\Catalog\Domain\Exception\InvalidStockException;
use App\Shared\Domain\Event\DomainEventInterface;

final class Product
{
    /** @var DomainEventInterface[] */
    private array $domainEvents = [];

    private function __construct(
        private readonly ProductId $id,
        private ProductName $name,
        private ProductDescription $description,
        private Money $price,
        private Category $category,
        private Brand $brand,
        private ProductAttributes $attributes,
        private Stock $stock,
        private ?string $imageUrl,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        ProductId $id,
        ProductName $name,
        ProductDescription $description,
        Money $price,
        Category $category,
        Brand $brand,
        ProductAttributes $attributes,
        Stock $stock,
        ?string $imageUrl,
    ): self {
        $product = new self(
            id: $id,
            name: $name,
            description: $description,
            price: $price,
            category: $category,
            brand: $brand,
            attributes: $attributes,
            stock: $stock,
            imageUrl: $imageUrl,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $product->record(new ProductCreated(
            productId: $id->value(),
            name: $name->value(),
            description: $description->value(),
            priceAmount: $price->amount(),
            priceCurrency: $price->currency()->value,
            category: $category->value,
            brand: $brand->value(),
            attributes: $attributes->toArray(),
            stock: $stock->quantity(),
            imageUrl: $imageUrl,
        ));

        return $product;
    }

    /** Reconstitutes from persistence — no events recorded. */
    public static function reconstitute(
        ProductId $id,
        ProductName $name,
        ProductDescription $description,
        Money $price,
        Category $category,
        Brand $brand,
        ProductAttributes $attributes,
        Stock $stock,
        ?string $imageUrl,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            name: $name,
            description: $description,
            price: $price,
            category: $category,
            brand: $brand,
            attributes: $attributes,
            stock: $stock,
            imageUrl: $imageUrl,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    public function update(
        ?ProductName $name,
        ?ProductDescription $description,
        ?Money $price,
        ?Category $category,
        ?Brand $brand,
        ?ProductAttributes $attributes,
        ?Stock $stock,
        ?string $imageUrl,
        bool $clearImageUrl = false,
    ): void {
        if ($name !== null) {
            $this->name = $name;
        }
        if ($description !== null) {
            $this->description = $description;
        }
        if ($price !== null) {
            $this->price = $price;
        }
        if ($category !== null) {
            $this->category = $category;
        }
        if ($brand !== null) {
            $this->brand = $brand;
        }
        if ($attributes !== null) {
            $this->attributes = $attributes;
        }
        if ($stock !== null) {
            $this->stock = $stock;
        }
        if ($clearImageUrl) {
            $this->imageUrl = null;
        } elseif ($imageUrl !== null) {
            $this->imageUrl = $imageUrl;
        }

        $this->updatedAt = new \DateTimeImmutable();

        $this->record(new ProductUpdated(
            productId: $this->id->value(),
            name: $this->name->value(),
            description: $this->description->value(),
            priceAmount: $this->price->amount(),
            priceCurrency: $this->price->currency()->value,
            category: $this->category->value,
            brand: $this->brand->value(),
            attributes: $this->attributes->toArray(),
            stock: $this->stock->quantity(),
            imageUrl: $this->imageUrl,
        ));
    }

    public function decreaseStock(int $units): void
    {
        $previousQuantity = $this->stock->quantity();
        $this->stock = $this->stock->decrease($units);
        $this->updatedAt = new \DateTimeImmutable();

        $this->record(new StockUpdated(
            productId: $this->id->value(),
            previousStock: $previousQuantity,
            newStock: $this->stock->quantity(),
        ));
    }

    public function delete(): void
    {
        $this->record(new ProductDeleted($this->id->value()));
    }

    public function hasStock(): bool
    {
        return $this->stock->isAvailable();
    }

    // ---- Getters ----

    public function id(): ProductId { return $this->id; }
    public function name(): ProductName { return $this->name; }
    public function description(): ProductDescription { return $this->description; }
    public function price(): Money { return $this->price; }
    public function category(): Category { return $this->category; }
    public function brand(): Brand { return $this->brand; }
    public function attributes(): ProductAttributes { return $this->attributes; }
    public function stock(): Stock { return $this->stock; }
    public function imageUrl(): ?string { return $this->imageUrl; }
    public function createdAt(): \DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    // ---- Domain Events ----

    private function record(DomainEventInterface $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return DomainEventInterface[] */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
