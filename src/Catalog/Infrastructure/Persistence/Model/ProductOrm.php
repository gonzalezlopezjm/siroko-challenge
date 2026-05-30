<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Persistence\Model;

/**
 * Infrastructure persistence model. Doctrine manages this class; the domain
 * Product aggregate is fully decoupled from it.
 */
class ProductOrm
{
    public string $id;
    public string $name;
    public string $description;
    public int $priceAmount;
    public string $priceCurrency;
    public string $category;
    public string $brand;
    public array $attributes;
    public int $stock;
    public ?string $imageUrl;
    public \DateTimeImmutable $createdAt;
    public \DateTimeImmutable $updatedAt;
}
