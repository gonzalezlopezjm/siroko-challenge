<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetProduct;

use App\Shared\Domain\Bus\Query\QueryInterface;

final class GetProductQuery implements QueryInterface
{
    public function __construct(public readonly string $productId) {}
}
