<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

enum Currency: string
{
    case EUR = 'EUR';
    case USD = 'USD';
    case GBP = 'GBP';
}
