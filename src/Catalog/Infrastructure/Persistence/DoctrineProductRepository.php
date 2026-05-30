<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence;

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
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Catalog\Infrastructure\Persistence\Model\ProductOrm;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineProductRepository implements ProductRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Product $product): void
    {
        $existing = $this->em->find(ProductOrm::class, $product->id()->value());

        if ($existing === null) {
            $this->em->persist($this->fromDomain($product));
        } else {
            $this->updateOrm($existing, $product);
        }

        $this->em->flush();
    }

    public function delete(Product $product): void
    {
        $orm = $this->em->find(ProductOrm::class, $product->id()->value());

        if ($orm !== null) {
            $this->em->remove($orm);
            $this->em->flush();
        }
    }

    public function findById(ProductId $id): ?Product
    {
        $orm = $this->em->find(ProductOrm::class, $id->value());

        return $orm !== null ? $this->toDomain($orm) : null;
    }

    public function existsByNameAndCategory(ProductName $name, Category $category, ?ProductId $excludeId = null): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(ProductOrm::class, 'p')
            ->where('p.name = :name')
            ->andWhere('p.category = :category')
            ->setParameter('name', $name->value())
            ->setParameter('category', $category->value);

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :excludeId')
               ->setParameter('excludeId', $excludeId->value());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function findByCriteria(?Category $category, ?string $brand, int $page, int $pageSize): array
    {
        $qb = $this->em->createQueryBuilder()
            ->select('p')
            ->from(ProductOrm::class, 'p')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $pageSize)
            ->setMaxResults($pageSize);

        if ($category !== null) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $category->value);
        }

        if ($brand !== null) {
            $qb->andWhere('p.brand = :brand')
               ->setParameter('brand', $brand);
        }

        return array_map(
            fn (ProductOrm $orm) => $this->toDomain($orm),
            $qb->getQuery()->getResult(),
        );
    }

    public function countByCriteria(?Category $category, ?string $brand): int
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(ProductOrm::class, 'p');

        if ($category !== null) {
            $qb->andWhere('p.category = :category')
               ->setParameter('category', $category->value);
        }

        if ($brand !== null) {
            $qb->andWhere('p.brand = :brand')
               ->setParameter('brand', $brand);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function toDomain(ProductOrm $orm): Product
    {
        return Product::reconstitute(
            id: ProductId::fromString($orm->id),
            name: ProductName::fromString($orm->name),
            description: ProductDescription::fromString($orm->description),
            price: Money::fromPersistence($orm->priceAmount, Currency::from($orm->priceCurrency)),
            category: Category::from($orm->category),
            brand: Brand::fromString($orm->brand),
            attributes: ProductAttributes::fromArray($orm->attributes),
            stock: Stock::of($orm->stock),
            imageUrl: $orm->imageUrl,
            createdAt: $orm->createdAt,
            updatedAt: $orm->updatedAt,
        );
    }

    private function fromDomain(Product $product): ProductOrm
    {
        $orm                = new ProductOrm();
        $orm->id            = $product->id()->value();
        $orm->name          = $product->name()->value();
        $orm->description   = $product->description()->value();
        $orm->priceAmount   = $product->price()->amount();
        $orm->priceCurrency = $product->price()->currency()->value;
        $orm->category      = $product->category()->value;
        $orm->brand         = $product->brand()->value();
        $orm->attributes    = $product->attributes()->toArray();
        $orm->stock         = $product->stock()->quantity();
        $orm->imageUrl      = $product->imageUrl();
        $orm->createdAt     = $product->createdAt();
        $orm->updatedAt     = $product->updatedAt();

        return $orm;
    }

    private function updateOrm(ProductOrm $orm, Product $product): void
    {
        $orm->name          = $product->name()->value();
        $orm->description   = $product->description()->value();
        $orm->priceAmount   = $product->price()->amount();
        $orm->priceCurrency = $product->price()->currency()->value;
        $orm->category      = $product->category()->value;
        $orm->brand         = $product->brand()->value();
        $orm->attributes    = $product->attributes()->toArray();
        $orm->stock         = $product->stock()->quantity();
        $orm->imageUrl      = $product->imageUrl();
        $orm->updatedAt     = $product->updatedAt();
    }
}
