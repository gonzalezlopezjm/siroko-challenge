<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

final class ProductDescription
{
    private function __construct(private readonly string $value) {}

    public static function fromString(string $value): self
    {
        if (mb_strlen($value) > 2000) {
            throw new \InvalidArgumentException('Product description cannot exceed 2000 characters.');
        }

        return new self($value);
    }

    public static function empty(): self
    {
        return new self('');
    }

    public function value(): string
    {
        return $this->value;
    }
}
