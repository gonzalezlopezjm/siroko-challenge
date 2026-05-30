<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\Command\CreateProduct\CreateProductCommand;
use App\Catalog\Application\Query\GetProduct\GetProductQuery;
use App\Catalog\Application\Query\ProductDTO;
use App\Catalog\Domain\Exception\DuplicateProductNameException;
use App\Catalog\Domain\Exception\InvalidPriceException;
use App\Catalog\Domain\Exception\InvalidStockException;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class CreateProductHandlerTest extends IntegrationTestCase
{
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bus = self::getContainer()->get(MessageBusInterface::class);
    }

    public function testCreateProductPersistsAndReturnsId(): void
    {
        $productId = $this->dispatch($this->bus, $this->validCommand());

        self::assertNotEmpty($productId);

        /** @var ProductDTO $dto */
        $dto = $this->dispatch($this->bus, new GetProductQuery($productId));

        self::assertSame($productId, $dto->id);
        self::assertSame('Mallot Mizuno', $dto->name);
        self::assertSame(4999, $dto->priceAmount);
        self::assertSame('EUR', $dto->priceCurrency);
        self::assertSame('CYCLING', $dto->category);
        self::assertSame('Mizuno', $dto->brand);
        self::assertSame(10, $dto->stock);
    }

    public function testCreateProductWithSameNameAndCategoryThrows(): void
    {
        $this->dispatch($this->bus, $this->validCommand());

        $this->expectException(DuplicateProductNameException::class);

        $this->dispatch($this->bus, $this->validCommand());
    }

    public function testCreateProductWithNegativePriceThrows(): void
    {
        $this->expectException(InvalidPriceException::class);

        $this->dispatch($this->bus, new CreateProductCommand(
            name: 'Camiseta',
            description: '',
            priceAmount: -100,
            priceCurrency: 'EUR',
            category: 'FITNESS',
            brand: 'Nike',
            attributes: [],
            stock: 5,
            imageUrl: null,
        ));
    }

    public function testCreateProductWithZeroPriceThrows(): void
    {
        $this->expectException(InvalidPriceException::class);

        $this->dispatch($this->bus, new CreateProductCommand(
            name: 'Camiseta',
            description: '',
            priceAmount: 0,
            priceCurrency: 'EUR',
            category: 'FITNESS',
            brand: 'Nike',
            attributes: [],
            stock: 5,
            imageUrl: null,
        ));
    }

    public function testCreateProductWithNegativeStockThrows(): void
    {
        $this->expectException(InvalidStockException::class);

        $this->dispatch($this->bus, new CreateProductCommand(
            name: 'Camiseta',
            description: '',
            priceAmount: 1000,
            priceCurrency: 'EUR',
            category: 'FITNESS',
            brand: 'Nike',
            attributes: [],
            stock: -1,
            imageUrl: null,
        ));
    }

    public function testSameNameInDifferentCategoryIsAllowed(): void
    {
        $this->dispatch($this->bus, $this->validCommand());

        $productId = $this->dispatch($this->bus, new CreateProductCommand(
            name: 'Mallot Mizuno',
            description: 'Otro producto',
            priceAmount: 2999,
            priceCurrency: 'EUR',
            category: 'FITNESS',
            brand: 'Mizuno',
            attributes: [],
            stock: 5,
            imageUrl: null,
        ));

        self::assertNotEmpty($productId);
    }

    private function validCommand(): CreateProductCommand
    {
        return new CreateProductCommand(
            name: 'Mallot Mizuno',
            description: 'Mallot de ciclismo profesional',
            priceAmount: 4999,
            priceCurrency: 'EUR',
            category: 'CYCLING',
            brand: 'Mizuno',
            attributes: ['color' => ['negro', 'azul']],
            stock: 10,
            imageUrl: null,
        );
    }
}
