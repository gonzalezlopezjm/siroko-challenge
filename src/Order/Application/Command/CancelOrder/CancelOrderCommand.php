<?php

declare(strict_types=1);

namespace App\Order\Application\Command\CancelOrder;

use App\Shared\Domain\Bus\Command\CommandInterface;

final class CancelOrderCommand implements CommandInterface
{
    public function __construct(public readonly string $orderId) {}
}
