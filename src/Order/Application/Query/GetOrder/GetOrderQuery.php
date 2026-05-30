<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetOrder;

use App\Shared\Domain\Bus\Query\QueryInterface;

final class GetOrderQuery implements QueryInterface
{
    public function __construct(public readonly string $orderId) {}
}
