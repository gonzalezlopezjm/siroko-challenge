<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Event\ProductCreated;
use App\Catalog\Domain\Event\ProductDeleted;
use App\Catalog\Domain\Event\ProductUpdated;
use App\Catalog\Domain\Event\StockUpdated;
use App\Catalog\Domain\Model\Brand;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\Currency;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductAttributes;
use App\Catalog\Domain\Model\ProductDescription;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\Stock;
use PHPUnit\Framework\TestCase;

final class ProductTest extends TestCase
{
    private function buildProduct(
        ?Stock $stock = null,
        ?string $imageUrl = null,
    ): Product {
        return Product::create(
            id: ProductId::generate(),
            name: ProductName::fromString('Mallot Mizuno'),
            description: ProductDescription::fromString('Mallot de ciclismo'),
            price: Money::of(4999, Currency::EUR),
            category: Category::CYCLING,
            brand: Brand::fromString('Mizuno'),
            attributes: ProductAttributes::fromArray(['color' => ['negro', 'azul']]),
            stock: $stock ?? Stock::of(10),
            imageUrl: $imageUrl,
        );
    }

    public function testCreateRaisesProductCreatedEvent(): void
    {
        $product = $this->buildProduct();
        $events  = $product->pullDomainEvents();

        self::assertCount(1, $events);
        self::assertInstanceOf(ProductCreated::class, $events[0]);

        $event = $events[0];
        self::assertSame('Mallot Mizuno', $event->name());
        self::assertSame(4999, $event->priceAmount());
        self::assertSame('CYCLING', $event->category());
        self::assertSame('Mizuno', $event->brand());
    }

    public function testPullDomainEventsClearsQueue(): void
    {
        $product = $this->buildProduct();
        $product->pullDomainEvents();

        self::assertEmpty($product->pullDomainEvents());
    }

    public function testUpdateRaisesProductUpdatedEvent(): void
    {
        $product = $this->buildProduct();
        $product->pullDomainEvents(); // clear creation event

        $product->update(
            name: ProductName::fromString('Mallot Actualizado'),
            description: null,
            price: Money::of(5999, Currency::EUR),
            category: null,
            brand: null,
            attributes: null,
            stock: null,
            imageUrl: null,
        );

        $events = $product->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductUpdated::class, $events[0]);

        $event = $events[0];
        self::assertSame('Mallot Actualizado', $event->name());
        self::assertSame(5999, $event->priceAmount());
    }

    public function testUpdateWithNoFieldsLeavesStateUnchanged(): void
    {
        $product = $this->buildProduct();
        $product->pullDomainEvents();

        $product->update(
            name: null,
            description: null,
            price: null,
            category: null,
            brand: null,
            attributes: null,
            stock: null,
            imageUrl: null,
        );

        self::assertSame('Mallot Mizuno', $product->name()->value());
        self::assertSame(4999, $product->price()->amount());
    }

    public function testDeleteRaisesProductDeletedEvent(): void
    {
        $product = $this->buildProduct();
        $product->pullDomainEvents();

        $product->delete();

        $events = $product->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(ProductDeleted::class, $events[0]);
        self::assertSame($product->id()->value(), $events[0]->productId());
    }

    public function testDecreaseStockRaisesStockUpdatedEvent(): void
    {
        $product = $this->buildProduct(Stock::of(10));
        $product->pullDomainEvents();

        $product->decreaseStock(3);

        self::assertSame(7, $product->stock()->quantity());

        $events = $product->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(StockUpdated::class, $events[0]);

        $event = $events[0];
        self::assertSame(10, $event->previousStock());
        self::assertSame(7, $event->newStock());
    }

    public function testHasStockReturnsTrueWhenStockPositive(): void
    {
        self::assertTrue($this->buildProduct(Stock::of(1))->hasStock());
    }

    public function testHasStockReturnsFalseWhenStockIsZero(): void
    {
        self::assertFalse($this->buildProduct(Stock::of(0))->hasStock());
    }

    public function testClearImageUrlViaUpdate(): void
    {
        $product = $this->buildProduct(imageUrl: 'https://example.com/image.jpg');
        $product->pullDomainEvents();

        $product->update(
            name: null,
            description: null,
            price: null,
            category: null,
            brand: null,
            attributes: null,
            stock: null,
            imageUrl: null,
            clearImageUrl: true,
        );

        self::assertNull($product->imageUrl());
    }
}
