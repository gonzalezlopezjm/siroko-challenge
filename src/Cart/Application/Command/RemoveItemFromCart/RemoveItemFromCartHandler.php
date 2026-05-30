<?php

declare(strict_types=1);

namespace App\Cart\Application\Command\RemoveItemFromCart;

use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RemoveItemFromCartHandler
{
    public function __construct(private readonly CartRepositoryInterface $repository) {}

    public function __invoke(RemoveItemFromCartCommand $command): void
    {
        $cartId = CartId::fromString($command->cartId);
        $cart   = $this->repository->findById($cartId);

        if ($cart === null) {
            throw CartNotFoundException::withId($cartId);
        }

        $cart->removeItem($command->productId);
        $this->repository->save($cart);
    }
}
