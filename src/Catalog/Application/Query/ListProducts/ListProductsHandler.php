<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\ListProducts;

use App\Catalog\Application\Query\GetProduct\GetProductHandler;
use App\Catalog\Application\Query\ProductListDTO;
use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ListProductsHandler
{
    public function __construct(private readonly ProductRepositoryInterface $repository) {}

    public function __invoke(ListProductsQuery $query): ProductListDTO
    {
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
    }
}
