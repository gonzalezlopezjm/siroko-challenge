<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query;

final class ProductListDTO
{
    /**
     * @param ProductDTO[] $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $pageSize,
    ) {}

    public function totalPages(): int
    {
        if ($this->pageSize === 0) {
            return 0;
        }

        return (int) ceil($this->total / $this->pageSize);
    }
}
