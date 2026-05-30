<?php

declare(strict_types=1);

namespace App\Order\Application\Command\CancelOrder;

use App\Order\Domain\Event\OrderCancelled;
use App\Order\Domain\Exception\OrderNotFoundException;
use App\Order\Domain\Model\OrderId;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class CancelOrderHandler
{
    public function __construct(
        private readonly OrderRepositoryInterface $repository,
        private readonly MessageBusInterface $eventBus,
    ) {}

    public function __invoke(CancelOrderCommand $command): void
    {
        $id    = OrderId::fromString($command->orderId);
        $order = $this->repository->findById($id);

        if ($order === null) {
            throw OrderNotFoundException::withId($id);
        }

        $order->cancel();
        $this->repository->save($order);

        $this->eventBus->dispatch(new OrderCancelled(
            orderId: $order->id()->value(),
            customerEmail: $order->customerEmail(),
            totalAmount: $order->totalAmount(),
            totalCurrency: $order->totalCurrency(),
        ));
    }
}
