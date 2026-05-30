<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\ListProducts;

use App\Catalog\Application\Query\GetProduct\GetProductHandler;
use App\Catalog\Application\Query\ProductListDTO;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsMessageHandler]
final class ListProductsHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
        #[Autowire(service: 'cache.products')]
        private readonly TagAwareCacheInterface $cache,
    ) {}

    public function __invoke(ListProductsQuery $query): ProductListDTO
    {
        $cacheKey = sprintf(
            'products.list.%s.%s.%d.%d',
            $query->category ?? '_',
            $query->brand    ?? '_',
            $query->page,
            $query->pageSize,
        );

        return $this->cache->get(
            $cacheKey,
            function (ItemInterface $item) use ($query): ProductListDTO {
                $item->tag(['product', 'products.list']);
                $item->expiresAfter(300);

                $category = $query->category !== null ? Category::from($query->category) : null;

                $products = $this->repository->findByCriteria(
                    category: $category,
                    brand: $query->brand,
                    page: $query->page,
                    pageSize: $query->pageSize,
                );

                $total = $this->repository->countByCriteria(
                    category: $category,
                    brand: $query->brand,
                );

                $dtos = array_map(
                    static fn ($product) => GetProductHandler::toDTO($product),
                    $products,
                );

                return new ProductListDTO(
                    items: $dtos,
                    total: $total,
                    page: $query->page,
                    pageSize: $query->pageSize,
                );
            },
        );
    }
}
