<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

final class InsufficientStockException extends \DomainException
{
    public function __construct(
        string $message,
        private readonly string $productId,
        private readonly int $available,
        private readonly int $requested,
    ) {
        parent::__construct($message);
    }

    public static function forProduct(string $productId, string $productName, int $available, int $requested): self
    {
        return new self(
            message: sprintf(
                "Product '%s' only has %d unit(s) in stock, %d requested.",
                $productName,
                $available,
                $requested,
            ),
            productId: $productId,
            available: $available,
            requested: $requested,
        );
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function available(): int
    {
        return $this->available;
    }

    public function requested(): int
    {
        return $this->requested;
    }
}
