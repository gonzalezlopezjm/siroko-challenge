<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

use App\Search\Domain\Model\ParsedSearchQuery;

interface QueryExpanderPort
{
    public function expand(string $query): ParsedSearchQuery;
}
