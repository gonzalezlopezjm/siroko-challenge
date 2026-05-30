<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

final class ProductAttributes
{
    /** @param array<string, string[]> $attributes */
    private function __construct(private readonly array $attributes) {}

    /** @param array<string, string[]> $attributes */
    public static function fromArray(array $attributes): self
    {
        return new self($attributes);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /** @return array<string, string[]> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    public function isEmpty(): bool
    {
        return $this->attributes === [];
    }
}
