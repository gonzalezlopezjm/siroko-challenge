<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateProduct;

use App\Catalog\Domain\Exception\DuplicateProductNameException;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\Brand;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\Currency;
use App\Catalog\Domain\Model\Money;
use App\Catalog\Domain\Model\ProductAttributes;
use App\Catalog\Domain\Model\ProductDescription;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;
use App\Catalog\Domain\Model\Stock;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsMessageHandler]
final class UpdateProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
        private readonly MessageBusInterface $eventBus,
        #[Autowire(service: 'cache.products')]
        private readonly TagAwareCacheInterface $cache,
    ) {}

    public function __invoke(UpdateProductCommand $command): void
    {
        $id      = ProductId::fromString($command->productId);
        $product = $this->repository->findById($id);

        if ($product === null) {
            throw ProductNotFoundException::withId($id);
        }

        $name     = $command->name !== null ? ProductName::fromString($command->name) : null;
        $category = $command->category !== null ? Category::from($command->category) : null;

        if ($name !== null) {
            $effectiveCategory = $category ?? $product->category();

            if ($this->repository->existsByNameAndCategory($name, $effectiveCategory, $id)) {
                throw DuplicateProductNameException::withNameAndCategory($name, $effectiveCategory);
            }
        }

        $price = null;
        if ($command->priceAmount !== null) {
            $currency = $command->priceCurrency !== null
                ? Currency::from($command->priceCurrency)
                : $product->price()->currency();
            $price = Money::of($command->priceAmount, $currency);
        }

        $product->update(
            name: $name,
            description: $command->description !== null ? ProductDescription::fromString($command->description) : null,
            price: $price,
            category: $category,
            brand: $command->brand !== null ? Brand::fromString($command->brand) : null,
            attributes: $command->attributes !== null ? ProductAttributes::fromArray($command->attributes) : null,
            stock: $command->stock !== null ? Stock::of($command->stock) : null,
            imageUrl: $command->imageUrl,
            clearImageUrl: $command->clearImageUrl,
        );

        $this->repository->save($product);

        $this->cache->invalidateTags(['product.' . $command->productId, 'products.list']);

        foreach ($product->pullDomainEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
