<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Model\ProductId;

final class ProductNotFoundException extends \DomainException
{
    public static function withId(ProductId $id): self
    {
        return new self(sprintf('Product with id "%s" was not found.', $id->value()));
    }
}
