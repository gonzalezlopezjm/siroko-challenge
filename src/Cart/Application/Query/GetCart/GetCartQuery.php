<?php

declare(strict_types=1);

namespace App\Cart\Application\Query\GetCart;

use App\Shared\Domain\Bus\Query\QueryInterface;

final class GetCartQuery implements QueryInterface
{
    public function __construct(public readonly string $cartId) {}
}
