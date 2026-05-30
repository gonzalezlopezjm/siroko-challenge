<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

final class ShippingAddress
{
    private function __construct(
        private readonly string $street,
        private readonly string $city,
        private readonly string $postalCode,
        private readonly string $country,
    ) {}

    public static function of(string $street, string $city, string $postalCode, string $country): self
    {
        if ($street === '' || $city === '' || $postalCode === '' || $country === '') {
            throw new \InvalidArgumentException('All shipping address fields are required.');
        }

        return new self($street, $city, $postalCode, $country);
    }

    public function street(): string
    {
        return $this->street;
    }

    public function city(): string
    {
        return $this->city;
    }

    public function postalCode(): string
    {
        return $this->postalCode;
    }

    public function country(): string
    {
        return $this->country;
    }
}
