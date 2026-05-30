<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Persistence\Model;

class CartOrm
{
    public string $id;
    public ?string $customerId;
    public string $itemsJson;
    public \DateTimeImmutable $createdAt;
    public \DateTimeImmutable $updatedAt;
}
