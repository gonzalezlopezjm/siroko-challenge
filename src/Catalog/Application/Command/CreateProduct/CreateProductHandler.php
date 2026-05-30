<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateProduct;

use App\Catalog\Domain\Exception\DuplicateProductNameException;
use App\Catalog\Domain\Model\Brand;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\Currency;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductAttributes;
use App\Catalog\Domain\Model\ProductDescription;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class CreateProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
        private readonly MessageBusInterface $eventBus,
    ) {}

    public function __invoke(CreateProductCommand $command): string
    {
        $id       = ProductId::generate();
        $name     = ProductName::fromString($command->name);
        $category = Category::from($command->category);

        if ($this->repository->existsByNameAndCategory($name, $category)) {
            throw DuplicateProductNameException::withNameAndCategory($name, $category);
        }

        $product = Product::create(
            id: $id,
            name: $name,
            description: ProductDescription::fromString($command->description),
            price: Money::of($command->priceAmount, Currency::from($command->priceCurrency)),
            category: $category,
            brand: Brand::fromString($command->brand),
            attributes: ProductAttributes::fromArray($command->attributes),
            stock: Stock::of($command->stock),
            imageUrl: $command->imageUrl,
        );

        $this->repository->save($product);

        foreach ($product->pullDomainEvents() as $event) {
            $this->eventBus->dispatch($event);
        }

        return $id->value();
    }
}
