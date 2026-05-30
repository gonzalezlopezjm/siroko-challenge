<?php

declare(strict_types=1);

namespace App\Cart\Application\Command\AddItemToCart;

use App\Cart\Domain\Exception\CartNotFoundException;
use App\Cart\Domain\Exception\InsufficientStockException;
use App\Cart\Domain\Model\CartId;
use App\Cart\Domain\Repository\CartRepositoryInterface;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class AddItemToCartHandler
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
    ) {}

    public function __invoke(AddItemToCartCommand $command): void
    {
        $cartId = CartId::fromString($command->cartId);
        $cart   = $this->cartRepository->findById($cartId);

        if ($cart === null) {
            throw CartNotFoundException::withId($cartId);
        }

        $productId = ProductId::fromString($command->productId);
        $product   = $this->productRepository->findById($productId);

        if ($product === null) {
            throw ProductNotFoundException::withId($productId);
        }

        if (!$product->stock()->isAvailable()) {
            throw InsufficientStockException::forProduct(
                productId: $product->id()->value(),
                productName: $product->name()->value(),
                available: $product->stock()->quantity(),
                requested: $command->quantity,
            );
        }

        $cart->addItem(
            productId: $product->id()->value(),
            productName: $product->name()->value(),
            unitPriceAmount: $product->price()->amount(),
            unitPriceCurrency: $product->price()->currency()->value,
            quantity: $command->quantity,
        );

        $this->cartRepository->save($cart);
    }
}
