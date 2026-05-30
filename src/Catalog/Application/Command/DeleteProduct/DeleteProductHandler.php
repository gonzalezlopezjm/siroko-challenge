<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DeleteProduct;

use App\Catalog\Domain\Exception\ProductNotFoundException;
use App\Catalog\Domain\Model\ProductId;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[AsMessageHandler]
final class DeleteProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $repository,
        private readonly MessageBusInterface $eventBus,
        #[Autowire(service: 'cache.products')]
        private readonly TagAwareCacheInterface $cache,
    ) {}

    public function __invoke(DeleteProductCommand $command): void
    {
        $id      = ProductId::fromString($command->productId);
        $product = $this->repository->findById($id);

        if ($product === null) {
            throw ProductNotFoundException::withId($id);
        }

        $product->delete();
        $this->repository->delete($product);

        $this->cache->invalidateTags(['product.' . $command->productId, 'products.list']);

        foreach ($product->pullDomainEvents() as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
