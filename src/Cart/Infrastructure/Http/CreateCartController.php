<?php

declare(strict_types=1);

namespace App\Cart\Infrastructure\Http;

use App\Cart\Application\Command\CreateCart\CreateCartCommand;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/carts', methods: ['POST'])]
final class CreateCartController
{
    public function __construct(private readonly MessageBusInterface $commandBus) {}

    public function __invoke(Request $request): JsonResponse
    {
        $body       = json_decode($request->getContent() ?: '{}', true) ?? [];
        $customerId = isset($body['customerId']) && $body['customerId'] !== null ? (string) $body['customerId'] : null;

        $envelope = $this->commandBus->dispatch(new CreateCartCommand($customerId));
        $cartId   = $envelope->last(HandledStamp::class)->getResult();

        return new JsonResponse(['cartId' => $cartId], Response::HTTP_CREATED);
    }
}
