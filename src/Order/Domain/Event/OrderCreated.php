<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;

final class OrderCreated implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly string $orderId,
        private readonly ?string $customerId,
        private readonly ?string $customerEmail,
        private readonly int $totalAmount,
        private readonly string $totalCurrency,
        private readonly array $lines,
        private readonly string $shippingStreet,
        private readonly string $shippingCity,
        private readonly string $shippingPostalCode,
        private readonly string $shippingCountry,
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function aggregateId(): string   { return $this->orderId; }
    public function occurredOn(): \DateTimeImmutable { return $this->occurredOn; }

    public function orderId(): string        { return $this->orderId; }
    public function customerId(): ?string    { return $this->customerId; }
    public function customerEmail(): ?string { return $this->customerEmail; }
    public function totalAmount(): int       { return $this->totalAmount; }
    public function totalCurrency(): string  { return $this->totalCurrency; }
    public function lines(): array           { return $this->lines; }
    public function shippingStreet(): string     { return $this->shippingStreet; }
    public function shippingCity(): string       { return $this->shippingCity; }
    public function shippingPostalCode(): string { return $this->shippingPostalCode; }
    public function shippingCountry(): string    { return $this->shippingCountry; }
}
