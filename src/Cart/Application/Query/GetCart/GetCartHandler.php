<?php

declare(strict_types=1);

namespace App\Cart\Application\Query\GetCart;

use App\Cart\Application\Query\CartDTO;
use App\Cart\Application\Query\CartItemDTO;
use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Model\Cart;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class GetCartHandler
{
    public function __construct(private readonly CartRepositoryInterface $repository) {}

    public function __invoke(GetCartQuery $query): CartDTO
    {
        $cartId = CartId::fromString($query->cartId);
        $cart   = $this->repository->findById($cartId);

        if ($cart === null) {
            throw CartNotFoundException::withId($cartId);
        }

        return self::toDTO($cart);
    }

    public static function toDTO(Cart $cart): CartDTO
    {
        $itemDTOs = array_map(
            static fn ($item) => new CartItemDTO(
                productId: $item->productId(),
                productName: $item->productName(),
                unitPriceAmount: $item->unitPriceAmount(),
                unitPriceCurrency: $item->unitPriceCurrency(),
                quantity: $item->quantity(),
                subtotalAmount: $item->subtotalAmount(),
                subtotalCurrency: $item->unitPriceCurrency(),
            ),
            $cart->items(),
        );

        return new CartDTO(
            id: $cart->id()->value(),
            customerId: $cart->customerId(),
            items: $itemDTOs,
            totalAmount: $cart->totalAmount(),
            totalCurrency: $cart->totalCurrency(),
            createdAt: $cart->createdAt()->format(\DateTimeInterface::ATOM),
            updatedAt: $cart->updatedAt()->format(\DateTimeInterface::ATOM),
        );
    }
}
