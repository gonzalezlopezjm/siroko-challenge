<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use App\Order\Domain\Exception\OrderAlreadyCancelledException;

final class Order
{
    /** @var OrderLine[] */
    private array $lines;

    private function __construct(
        private readonly OrderId $id,
        private readonly ?string $customerId,
        private readonly ?string $customerEmail,
        array $lines,
        private readonly int $totalAmount,
        private readonly string $totalCurrency,
        private OrderStatus $status,
        private readonly ShippingAddress $shippingAddress,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->lines = $lines;
    }

    /** @param OrderLine[] $lines */
    public static function create(
        OrderId $id,
        ?string $customerId,
        ?string $customerEmail,
        array $lines,
        ShippingAddress $shippingAddress,
    ): self {
        $totalAmount   = array_sum(array_map(fn (OrderLine $l) => $l->subtotalAmount(), $lines));
        $totalCurrency = $lines[0]->unitPriceCurrency();

        return new self(
            id: $id,
            customerId: $customerId,
            customerEmail: $customerEmail,
            lines: $lines,
            totalAmount: $totalAmount,
            totalCurrency: $totalCurrency,
            status: OrderStatus::PENDING,
            shippingAddress: $shippingAddress,
            createdAt: new \DateTimeImmutable(),
        );
    }

    /** @param OrderLine[] $lines */
    public static function reconstitute(
        OrderId $id,
        ?string $customerId,
        ?string $customerEmail,
        array $lines,
        int $totalAmount,
        string $totalCurrency,
        OrderStatus $status,
        ShippingAddress $shippingAddress,
        \DateTimeImmutable $createdAt,
    ): self {
        return new self($id, $customerId, $customerEmail, $lines, $totalAmount, $totalCurrency, $status, $shippingAddress, $createdAt);
    }

    public function cancel(): void
    {
        if ($this->status === OrderStatus::CANCELLED) {
            throw OrderAlreadyCancelledException::withId($this->id, $this->status);
        }

        $this->status = OrderStatus::CANCELLED;
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function customerId(): ?string
    {
        return $this->customerId;
    }

    public function customerEmail(): ?string
    {
        return $this->customerEmail;
    }

    /** @return OrderLine[] */
    public function lines(): array
    {
        return $this->lines;
    }

    public function totalAmount(): int
    {
        return $this->totalAmount;
    }

    public function totalCurrency(): string
    {
        return $this->totalCurrency;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function shippingAddress(): ShippingAddress
    {
        return $this->shippingAddress;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
