<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Model;

enum Category: string
{
    case CYCLING    = 'CYCLING';
    case FITNESS    = 'FITNESS';
    case APPAREL    = 'APPAREL';
    case ACCESSORIES = 'ACCESSORIES';
}
