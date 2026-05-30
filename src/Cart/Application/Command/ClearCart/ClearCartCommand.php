<?php

declare(strict_types=1);

namespace App\Cart\Application\Command\ClearCart;

use App\Shared\Domain\Bus\Command\CommandInterface;

final class ClearCartCommand implements CommandInterface
{
    public function __construct(public readonly string $cartId) {}
}
