<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

final class CartIsEmptyException extends \DomainException
{
    public static function withCartId(string $cartId): self
    {
        return new self(sprintf('Cannot checkout an empty cart.'));
    }
}
