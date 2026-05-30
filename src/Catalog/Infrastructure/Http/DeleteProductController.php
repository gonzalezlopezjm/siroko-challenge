<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\Command\DeleteProduct\DeleteProductCommand;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/products/{id}', methods: ['DELETE'])]
final class DeleteProductController
{
    public function __construct(private readonly MessageBusInterface $commandBus) {}

    public function __invoke(string $id): JsonResponse
    {
        try {
            $this->commandBus->dispatch(new DeleteProductCommand($id));

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $e) {
            $cause = $e->getPrevious() ?? $e;
            if ($cause instanceof ProductNotFoundException) {
                return new JsonResponse(
                    ['error' => ['code' => 'product_not_found', 'message' => $cause->getMessage()]],
                    Response::HTTP_NOT_FOUND,
                );
            }
            if ($cause instanceof \InvalidArgumentException) {
                return new JsonResponse(
                    ['error' => ['code' => 'invalid_id', 'message' => $cause->getMessage()]],
                    Response::HTTP_BAD_REQUEST,
                );
            }
            throw $e;
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_id', 'message' => $e->getMessage()]],
                Response::HTTP_BAD_REQUEST,
            );
        }
    }
}
