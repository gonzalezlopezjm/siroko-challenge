<?php

declare(strict_types=1);

namespace App\Cart\Domain\Exception;

use App\Cart\Domain\Model\CartId;

final class CartNotFoundException extends \DomainException
{
    public static function withId(CartId $id): self
    {
        return new self(sprintf('Cart "%s" not found.', $id->value()));
    }
}
