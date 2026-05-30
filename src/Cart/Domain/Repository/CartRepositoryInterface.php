<?php

declare(strict_types=1);

namespace App\Cart\Domain\Repository;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;

interface CartRepositoryInterface
{
    public function save(Cart $cart): void;

    public function findById(CartId $id): ?Cart;
}
