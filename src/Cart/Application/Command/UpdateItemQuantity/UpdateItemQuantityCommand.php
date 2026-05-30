<?php

declare(strict_types=1);

namespace App\Cart\Application\Command\UpdateItemQuantity;

use App\Shared\Domain\Bus\Command\CommandInterface;

final class UpdateItemQuantityCommand implements CommandInterface
{
    public function __construct(
        public readonly string $cartId,
        public readonly string $productId,
        public readonly int $quantity,
    ) {}
}
