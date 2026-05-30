<?php

declare(strict_types=1);

namespace App\Cart\Application\Command\CreateCart;

use App\Shared\Domain\Bus\Command\CommandInterface;

final class CreateCartCommand implements CommandInterface
{
    public function __construct(public readonly ?string $customerId = null) {}
}
