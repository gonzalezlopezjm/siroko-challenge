<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\Command\CreateProduct\CreateProductCommand;
use App\Catalog\Application\Query\GetProduct\GetProductQuery;
use App\Catalog\Application\Query\ListProducts\ListProductsQuery;
use App\Catalog\Application\Query\ProductDTO;
use App\Catalog\Application\Query\ProductListDTO;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class ListGetProductHandlerTest extends IntegrationTestCase
{
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bus = self::getContainer()->get(MessageBusInterface::class);
    }

    public function testGetProductReturnsCorrectDTO(): void
    {
        $productId = $this->createProduct('Mallot Mizuno', 'CYCLING');

        /** @var ProductDTO $dto */
        $dto = $this->dispatch($this->bus, new GetProductQuery($productId));

        self::assertSame($productId, $dto->id);
        self::assertSame('Mallot Mizuno', $dto->name);
        self::assertSame(4999, $dto->priceAmount);
        self::assertSame('EUR', $dto->priceCurrency);
        self::assertSame('CYCLING', $dto->category);
        self::assertSame(10, $dto->stock);
    }

    public function testGetProductNotFoundThrows(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->dispatch($this->bus, new GetProductQuery('550e8400-e29b-41d4-a716-446655440000'));
    }

    public function testListProductsWithoutFiltersReturnsAll(): void
    {
        $this->createProduct('Mallot Mizuno', 'CYCLING');
        $this->createProduct('Camiseta Nike', 'FITNESS');

        /** @var ProductListDTO $list */
        $list = $this->dispatch($this->bus, new ListProductsQuery(
            category: null,
            brand: null,
            page: 1,
            pageSize: 10,
        ));

        self::assertSame(2, $list->total);
        self::assertCount(2, $list->items);
    }

    public function testListProductsFiltersByCategory(): void
    {
        $this->createProduct('Mallot Mizuno', 'CYCLING');
        $this->createProduct('Camiseta Nike', 'FITNESS');

        /** @var ProductListDTO $list */
        $list = $this->dispatch($this->bus, new ListProductsQuery(
            category: 'CYCLING',
            brand: null,
            page: 1,
            pageSize: 10,
        ));

        self::assertSame(1, $list->total);
        self::assertSame('Mallot Mizuno', $list->items[0]->name);
    }

    public function testListProductsPaginationWorks(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createProduct("Producto {$i}", 'CYCLING');
        }

        /** @var ProductListDTO $list */
        $list = $this->dispatch($this->bus, new ListProductsQuery(
            category: null,
            brand: null,
            page: 2,
            pageSize: 2,
        ));

        self::assertSame(5, $list->total);
        self::assertCount(2, $list->items);
        self::assertSame(3, $list->totalPages());
    }

    private function createProduct(string $name, string $category, string $brand = 'Mizuno'): string
    {
        return $this->dispatch($this->bus, new CreateProductCommand(
            name: $name,
            description: 'Desc',
            priceAmount: 4999,
            priceCurrency: 'EUR',
            category: $category,
            brand: $brand,
            attributes: [],
            stock: 10,
            imageUrl: null,
        ));
    }
}
