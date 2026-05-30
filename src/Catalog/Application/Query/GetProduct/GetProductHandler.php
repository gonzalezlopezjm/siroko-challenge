<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetProduct;

use App\Catalog\Application\Query\ProductDTO;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsMessageHandler]
final class GetProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
        #[Autowire(service: 'cache.products')]
        private readonly TagAwareCacheInterface $cache,
    ) {}

    public function __invoke(GetProductQuery $query): ProductDTO
    {
        return $this->cache->get(
            'product.' . $query->productId,
            function (ItemInterface $item) use ($query): ProductDTO {
                $item->tag(['product', 'product.' . $query->productId]);

                $id      = ProductId::fromString($query->productId);
                $product = $this->repository->findById($id);

                if ($product === null) {
                    throw ProductNotFoundException::withId($id);
                }

                return self::toDTO($product);
            },
        );
    }

    public static function toDTO(Product $product): ProductDTO
    {
        return new ProductDTO(
            id: $product->id()->value(),
            name: $product->name()->value(),
            description: $product->description()->value(),
            priceAmount: $product->price()->amount(),
            priceCurrency: $product->price()->currency()->value,
            category: $product->category()->value,
            brand: $product->brand()->value(),
            attributes: $product->attributes()->toArray(),
            stock: $product->stock()->quantity(),
            imageUrl: $product->imageUrl(),
            createdAt: $product->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $product->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
