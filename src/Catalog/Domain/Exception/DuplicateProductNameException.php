<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Model\Category;
use App\Catalog\Domain\Model\ProductName;

final class DuplicateProductNameException extends \DomainException
{
    public static function withNameAndCategory(ProductName $name, Category $category): self
    {
        return new self(sprintf(
            'A product named "%s" already exists in category "%s".',
            $name->value(),
            $category->value,
        ));
    }
}
