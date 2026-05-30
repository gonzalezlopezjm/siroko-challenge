<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Model\OrderStatus;

final class OrderAlreadyCancelledException extends \DomainException
{
    public static function withId(OrderId $id, OrderStatus $currentStatus): self
    {
        return new self(sprintf(
            "Order '%s' cannot be cancelled from status '%s'.",
            $id->value(),
            $currentStatus->value,
        ));
    }
}
