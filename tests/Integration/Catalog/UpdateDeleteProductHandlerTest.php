<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\Command\CreateProduct\CreateProductCommand;
use App\Catalog\Application\Command\DeleteProduct\DeleteProductCommand;
use App\Catalog\Application\Command\UpdateProduct\UpdateProductCommand;
use App\Catalog\Application\Query\GetProduct\GetProductQuery;
use App\Catalog\Application\Query\ProductDTO;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class UpdateDeleteProductHandlerTest extends IntegrationTestCase
{
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bus = self::getContainer()->get(MessageBusInterface::class);
    }

    public function testUpdateProductChangesFields(): void
    {
        $productId = $this->createProduct();

        $this->dispatch($this->bus, new UpdateProductCommand(
            productId: $productId,
            name: 'Mallot Actualizado',
            description: null,
            priceAmount: 5999,
            priceCurrency: null,
            category: null,
            brand: null,
            attributes: null,
            stock: null,
            imageUrl: null,
        ));

        /** @var ProductDTO $dto */
        $dto = $this->dispatch($this->bus, new GetProductQuery($productId));

        self::assertSame('Mallot Actualizado', $dto->name);
        self::assertSame(5999, $dto->priceAmount);
    }

    public function testUpdateProductNotFoundThrows(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->dispatch($this->bus, new UpdateProductCommand(
            productId: '550e8400-e29b-41d4-a716-446655440000',
            name: 'Nuevo nombre',
            description: null,
            priceAmount: null,
            priceCurrency: null,
            category: null,
            brand: null,
            attributes: null,
            stock: null,
            imageUrl: null,
        ));
    }

    public function testDeleteProductRemovesIt(): void
    {
        $productId = $this->createProduct();

        $this->dispatch($this->bus, new DeleteProductCommand($productId));

        $this->expectException(ProductNotFoundException::class);
        $this->dispatch($this->bus, new GetProductQuery($productId));
    }

    public function testDeleteProductNotFoundThrows(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->dispatch($this->bus, new DeleteProductCommand('550e8400-e29b-41d4-a716-446655440000'));
    }

    private function createProduct(string $name = 'Mallot Mizuno'): string
    {
        return $this->dispatch($this->bus, new CreateProductCommand(
            name: $name,
            description: 'Desc',
            priceAmount: 4999,
            priceCurrency: 'EUR',
            category: 'CYCLING',
            brand: 'Mizuno',
            attributes: [],
            stock: 10,
            imageUrl: null,
        ));
    }
}
