<?php

declare(strict_types=1);

namespace App\Cart\Application\Command\ClearCart;

use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ClearCartHandler
{
    public function __construct(private readonly CartRepositoryInterface $repository) {}

    public function __invoke(ClearCartCommand $command): void
    {
        $cartId = CartId::fromString($command->cartId);
        $cart   = $this->repository->findById($cartId);

        if ($cart === null) {
            throw CartNotFoundException::withId($cartId);
        }

        $cart->clear();
        $this->repository->save($cart);
    }
}
