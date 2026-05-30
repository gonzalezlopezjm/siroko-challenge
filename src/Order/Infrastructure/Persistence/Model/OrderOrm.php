<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Persistence\Model;

final class OrderOrm
{
    public string $id;
    public ?string $customerId;
    public ?string $customerEmail;
    public int $totalAmount;
    public string $totalCurrency;
    public string $status;
    public string $shippingStreet;
    public string $shippingCity;
    public string $shippingPostalCode;
    public string $shippingCountry;
    public string $linesJson;
    public \DateTimeImmutable $createdAt;
}
