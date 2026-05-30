<?php

declare(strict_types=1);

namespace App\Cart\Domain\Model;

use App\Cart\Domain\Exception\CartLineItemLimitExceededException;
use App\Cart\Domain\Exception\ItemNotInCartException;

final class Cart
{
    private const MAX_LINES    = 50;
    private const MAX_QUANTITY = 99;

    /** @var CartItem[] */
    private array $items;

    private function __construct(
        private readonly CartId $id,
        private readonly ?string $customerId,
        array $items,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
        $this->items = $items;
    }

    public static function create(CartId $id, ?string $customerId): self
    {
        return new self($id, $customerId, [], new \DateTimeImmutable(), new \DateTimeImmutable());
    }

    public static function reconstitute(
        CartId $id,
        ?string $customerId,
        array $items,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $customerId, $items, $createdAt, $updatedAt);
    }

    public function addItem(
        string $productId,
        string $productName,
        int $unitPriceAmount,
        string $unitPriceCurrency,
        int $quantity,
    ): void {
        foreach ($this->items as $key => $item) {
            if ($item->productId() === $productId) {
                $newQty = $item->quantity() + $quantity;
                if ($newQty > self::MAX_QUANTITY) {
                    $newQty = self::MAX_QUANTITY;
                }
                $this->items[$key] = $item->withQuantity($newQty);
                $this->touch();

                return;
            }
        }

        if (count($this->items) >= self::MAX_LINES) {
            throw CartLineItemLimitExceededException::becauseMaxLinesReached(self::MAX_LINES);
        }

        $this->items[] = new CartItem($productId, $productName, $unitPriceAmount, $unitPriceCurrency, $quantity);
        $this->touch();
    }

    public function updateItemQuantity(string $productId, int $quantity): void
    {
        foreach ($this->items as $key => $item) {
            if ($item->productId() === $productId) {
                $this->items[$key] = $item->withQuantity(min($quantity, self::MAX_QUANTITY));
                $this->touch();

                return;
            }
        }

        throw ItemNotInCartException::withProductId($productId);
    }

    public function removeItem(string $productId): void
    {
        foreach ($this->items as $key => $item) {
            if ($item->productId() === $productId) {
                array_splice($this->items, $key, 1);
                $this->touch();

                return;
            }
        }

        throw ItemNotInCartException::withProductId($productId);
    }

    public function clear(): void
    {
        $this->items = [];
        $this->touch();
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /** @return CartItem[] */
    public function items(): array
    {
        return array_values($this->items);
    }

    public function totalAmount(): int
    {
        return array_sum(array_map(fn (CartItem $i) => $i->subtotalAmount(), $this->items));
    }

    public function totalCurrency(): string
    {
        return ($this->items[0] ?? null)?->unitPriceCurrency() ?? 'EUR';
    }

    public function id(): CartId
    {
        return $this->id;
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
