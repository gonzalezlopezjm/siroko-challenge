<?php

declare(strict_types=1);

namespace App\Cart\Application\Command\CreateCart;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CreateCartHandler
{
    public function __construct(private readonly CartRepositoryInterface $repository) {}

    public function __invoke(CreateCartCommand $command): string
    {
        $id   = CartId::generate();
        $cart = Cart::create($id, $command->customerId);

        $this->repository->save($cart);

        return $id->value();
    }
}
