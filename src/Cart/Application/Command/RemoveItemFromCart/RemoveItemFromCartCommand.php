<?php

declare(strict_types=1);

namespace App\Cart\Application\Command\RemoveItemFromCart;

use App\Shared\Domain\Bus\Command\CommandInterface;

final class RemoveItemFromCartCommand implements CommandInterface
{
    public function __construct(
        public readonly string $cartId,
        public readonly string $productId,
    ) {}
}
