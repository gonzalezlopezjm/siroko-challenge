<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

final class InvalidStockException extends \DomainException
{
    public static function becauseQuantityCannotBeNegative(int $quantity): self
    {
        return new self(sprintf('Stock quantity cannot be negative, %d given.', $quantity));
    }

    public static function becauseInsufficientStock(int $available, int $requested): self
    {
        return new self(sprintf(
            'Insufficient stock: %d available, %d requested.',
            $available,
            $requested,
        ));
    }
}
