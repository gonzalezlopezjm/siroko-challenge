<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

use Ramsey\Uuid\Uuid;

final class OrderId
{
    private function __construct(private readonly string $value) {}

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        if (!Uuid::isValid($value)) {
            throw new \InvalidArgumentException(sprintf('Invalid OrderId: "%s".', $value));
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }
}
