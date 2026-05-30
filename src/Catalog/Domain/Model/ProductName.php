<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

final class ProductName
{
    private function __construct(private readonly string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Product name cannot be empty.');
        }

        if (mb_strlen($trimmed) > 255) {
            throw new \InvalidArgumentException('Product name cannot exceed 255 characters.');
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
