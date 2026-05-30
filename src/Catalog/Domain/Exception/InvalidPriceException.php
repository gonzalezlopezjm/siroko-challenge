<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

final class InvalidPriceException extends \DomainException
{
    public static function becauseAmountMustBePositive(int $amount): self
    {
        return new self(sprintf(
            'Price amount must be a positive integer (in cents), %d given.',
            $amount,
        ));
    }
}
