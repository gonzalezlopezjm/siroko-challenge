<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

use App\Catalog\Domain\Exception\InvalidPriceException;

final class Money
{
    private function __construct(
        private readonly int $amount,
        private readonly Currency $currency,
    ) {}

    public static function of(int $amount, Currency $currency): self
    {
        if ($amount <= 0) {
            throw InvalidPriceException::becauseAmountMustBePositive($amount);
        }

        return new self($amount, $currency);
    }

    /** Constructs without validation — for ORM reconstruction only. */
    public static function fromPersistence(int $amount, Currency $currency): self
    {
        return new self($amount, $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}
