<?php

declare(strict_types=1);

namespace App\Order\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;

final class OrderCancelled implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly string $orderId,
        private readonly ?string $customerEmail,
        private readonly int $totalAmount,
        private readonly string $totalCurrency,
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function aggregateId(): string   { return $this->orderId; }
    public function occurredOn(): \DateTimeImmutable { return $this->occurredOn; }

    public function orderId(): string        { return $this->orderId; }
    public function customerEmail(): ?string { return $this->customerEmail; }
    public function totalAmount(): int       { return $this->totalAmount; }
    public function totalCurrency(): string  { return $this->totalCurrency; }
}
