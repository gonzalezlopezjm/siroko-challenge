<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

final class OrderLine
{
    private function __construct(
        private readonly OrderLineId $id,
        private readonly string $productId,
        private readonly string $productName,
        private readonly int $unitPriceAmount,
        private readonly string $unitPriceCurrency,
        private readonly int $quantity,
    ) {}

    public static function create(
        OrderLineId $id,
        string $productId,
        string $productName,
        int $unitPriceAmount,
        string $unitPriceCurrency,
        int $quantity,
    ): self {
        return new self($id, $productId, $productName, $unitPriceAmount, $unitPriceCurrency, $quantity);
    }

    public static function reconstitute(
        OrderLineId $id,
        string $productId,
        string $productName,
        int $unitPriceAmount,
        string $unitPriceCurrency,
        int $quantity,
    ): self {
        return new self($id, $productId, $productName, $unitPriceAmount, $unitPriceCurrency, $quantity);
    }

    public function id(): OrderLineId
    {
        return $this->id;
    }

    public function productId(): string
    {
        return $this->productId;
    }

    public function productName(): string
    {
        return $this->productName;
    }

    public function unitPriceAmount(): int
    {
        return $this->unitPriceAmount;
    }

    public function unitPriceCurrency(): string
    {
        return $this->unitPriceCurrency;
    }

    public function quantity(): int
    {
        return $this->quantity;
    }

    public function subtotalAmount(): int
    {
        return $this->unitPriceAmount * $this->quantity;
    }
}
