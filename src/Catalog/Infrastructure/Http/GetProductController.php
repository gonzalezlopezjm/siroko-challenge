<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\Query\GetProduct\GetProductQuery;
use App\Catalog\Application\Query\ProductDTO;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/products/{id}', methods: ['GET'])]
final class GetProductController
{
    public function __construct(private readonly MessageBusInterface $queryBus) {}

    public function __invoke(string $id): JsonResponse
    {
        try {
            $envelope = $this->queryBus->dispatch(new GetProductQuery($id));
            /** @var ProductDTO $dto */
            $dto = $envelope->last(HandledStamp::class)->getResult();

            return new JsonResponse($this->serialize($dto));
        } catch (HandlerFailedException $e) {
            $cause = $e->getPrevious();
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

    private function serialize(ProductDTO $dto): array
    {
        return [
            'id'            => $dto->id,
            'name'          => $dto->name,
            'description'   => $dto->description,
            'price'         => [
                'amount'   => $dto->priceAmount,
                'currency' => $dto->priceCurrency,
            ],
            'category'      => $dto->category,
            'brand'         => $dto->brand,
            'attributes'    => $dto->attributes,
            'stock'         => $dto->stock,
            'imageUrl'      => $dto->imageUrl,
            'createdAt'     => $dto->createdAt,
            'updatedAt'     => $dto->updatedAt,
        ];
    }
}
