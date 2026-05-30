<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Persistence;

use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Model\CartItem;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Cart\Infrastructure\Persistence\Model\CartOrm;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineCartRepository implements CartRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function save(Cart $cart): void
    {
        $orm = $this->em->find(CartOrm::class, $cart->id()->value());

        if ($orm === null) {
            $orm = $this->fromDomain($cart);
            $this->em->persist($orm);
        } else {
            $this->updateOrm($orm, $cart);
        }

        $this->em->flush();
    }

    public function findById(CartId $id): ?Cart
    {
        $orm = $this->em->find(CartOrm::class, $id->value());

        if ($orm === null) {
            return null;
        }

        return $this->toDomain($orm);
    }

    private function toDomain(CartOrm $orm): Cart
    {
        $items = array_map(
            static fn (array $data) => CartItem::fromArray($data),
            json_decode($orm->itemsJson, true) ?? [],
        );

        return Cart::reconstitute(
            id: CartId::fromString($orm->id),
            customerId: $orm->customerId,
            items: $items,
            createdAt: $orm->createdAt,
            updatedAt: $orm->updatedAt,
        );
    }

    private function fromDomain(Cart $cart): CartOrm
    {
        $orm             = new CartOrm();
        $orm->id         = $cart->id()->value();
        $orm->customerId = $cart->customerId();
        $orm->itemsJson  = json_encode(array_map(fn (CartItem $i) => $i->toArray(), $cart->items()));
        $orm->createdAt  = $cart->createdAt();
        $orm->updatedAt  = $cart->updatedAt();

        return $orm;
    }

    private function updateOrm(CartOrm $orm, Cart $cart): void
    {
        $orm->itemsJson = json_encode(array_map(fn (CartItem $i) => $i->toArray(), $cart->items()));
        $orm->updatedAt = $cart->updatedAt();
    }
}
