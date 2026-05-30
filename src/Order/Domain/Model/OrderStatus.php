<?php

declare(strict_types=1);

namespace App\Order\Domain\Model;

enum OrderStatus: string
{
    case PENDING   = 'PENDING';
    case CONFIRMED = 'CONFIRMED';
    case CANCELLED = 'CANCELLED';
}
