<?php

declare(strict_types=1);

namespace App\Order\Application\Command\CheckoutOrder;

use App\Shared\Domain\Bus\Command\CommandInterface;

final class CheckoutOrderCommand implements CommandInterface
{
    public function __construct(
        public readonly string $cartId,
        public readonly ?string $customerId,
        public readonly ?string $customerEmail,
        public readonly string $shippingStreet,
        public readonly string $shippingCity,
        public readonly string $shippingPostalCode,
        public readonly string $shippingCountry,
    ) {}
}
