<?php

declare(strict_types=1);

namespace App\Cart\Domain\Model;

final class CartItem
{
    public function __construct(
        private readonly string $productId,
        private readonly string $productName,
        private readonly int $unitPriceAmount,
        private readonly string $unitPriceCurrency,
        private readonly int $quantity,
    ) {}

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

    public function withQuantity(int $quantity): self
    {
        return new self(
            $this->productId,
            $this->productName,
            $this->unitPriceAmount,
            $this->unitPriceCurrency,
            $quantity,
        );
    }

    public function toArray(): array
    {
        return [
            'productId'         => $this->productId,
            'productName'       => $this->productName,
            'unitPriceAmount'   => $this->unitPriceAmount,
            'unitPriceCurrency' => $this->unitPriceCurrency,
            'quantity'          => $this->quantity,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productId: $data['productId'],
            productName: $data['productName'],
            unitPriceAmount: (int) $data['unitPriceAmount'],
            unitPriceCurrency: $data['unitPriceCurrency'],
            quantity: (int) $data['quantity'],
        );
    }
}
