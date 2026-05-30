<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

final class Brand
{
    private function __construct(private readonly string $value) {}

    public static function fromString(string $value): self
    {
        $trimmed = trim($value);

        if ($trimmed === '') {
            throw new \InvalidArgumentException('Brand cannot be empty.');
        }

        if (mb_strlen($trimmed) > 100) {
            throw new \InvalidArgumentException('Brand cannot exceed 100 characters.');
        }

        return new self($trimmed);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
