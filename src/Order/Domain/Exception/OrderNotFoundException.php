<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

use App\Order\Domain\Model\OrderId;

final class OrderNotFoundException extends \DomainException
{
    public static function withId(OrderId $id): self
    {
        return new self(sprintf('Order "%s" not found.', $id->value()));
    }
}
