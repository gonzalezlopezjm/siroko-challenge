<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Exception\InvalidStockException;

final class Stock
{
    private function __construct(private readonly int $quantity) {}

    public static function of(int $quantity): self
    {
        if ($quantity < 0) {
            throw InvalidStockException::becauseQuantityCannotBeNegative($quantity);
        }

        return new self($quantity);
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function isAvailable(): bool
    {
        return $this->quantity > 0;
    }

    public function decrease(int $units): self
    {
        $new = $this->quantity - $units;

        if ($new < 0) {
            throw InvalidStockException::becauseInsufficientStock($this->quantity, $units);
        }

        return new self($new);
    }
}
