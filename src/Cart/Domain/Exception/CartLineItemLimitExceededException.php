<?php

declare(strict_types=1);

namespace App\Cart\Domain\Exception;

final class CartLineItemLimitExceededException extends \DomainException
{
    public static function becauseMaxLinesReached(int $max): self
    {
        return new self(sprintf('Cart cannot have more than %d different items.', $max));
    }
}
