<?php

declare(strict_types=1);

namespace App\Cart\Domain\Exception;

final class ItemNotInCartException extends \DomainException
{
    public static function withProductId(string $productId): self
    {
        return new self(sprintf('Product "%s" is not in the cart.', $productId));
    }
}
