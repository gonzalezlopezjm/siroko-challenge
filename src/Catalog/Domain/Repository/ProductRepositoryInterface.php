<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\Product;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Model\ProductName;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function delete(Product $product): void;

    public function findById(ProductId $id): ?Product;

    public function existsByNameAndCategory(ProductName $name, Category $category, ?ProductId $excludeId = null): bool;

    /**
     * @return Product[]
     */
    public function findByCriteria(?Category $category, ?string $brand, int $page, int $pageSize): array;

    public function countByCriteria(?Category $category, ?string $brand): int;
}
