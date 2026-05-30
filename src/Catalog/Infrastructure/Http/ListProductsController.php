<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\Query\ListProducts\ListProductsQuery;
use App\Catalog\Application\Query\ProductDTO;
use App\Catalog\Application\Query\ProductListDTO;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/products', methods: ['GET'])]
final class ListProductsController
{
    public function __construct(private readonly MessageBusInterface $queryBus) {}

    public function __invoke(Request $request): JsonResponse
    {
        $page     = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 20)));
        $category = $request->query->get('category');
        $brand    = $request->query->get('brand');

        try {
            $envelope = $this->queryBus->dispatch(new ListProductsQuery(
                category: $category !== '' ? $category : null,
                brand: $brand !== '' ? $brand : null,
                page: $page,
                pageSize: $pageSize,
            ));

            /** @var ProductListDTO $list */
            $list = $envelope->last(HandledStamp::class)->getResult();

            return new JsonResponse([
                'data'       => array_map(fn (ProductDTO $dto) => $this->serializeItem($dto), $list->items),
                'pagination' => [
                    'page'       => $list->page,
                    'pageSize'   => $list->pageSize,
                    'total'      => $list->total,
                    'totalPages' => $list->totalPages(),
                ],
            ]);
        } catch (HandlerFailedException $e) {
            $cause = $e->getPrevious() ?? $e;
            if ($cause instanceof \ValueError) {
                return new JsonResponse(
                    ['error' => ['code' => 'invalid_enum_value', 'message' => $cause->getMessage()]],
                    422,
                );
            }
            throw $e;
        } catch (\ValueError $e) {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_enum_value', 'message' => $e->getMessage()]],
                422,
            );
        }
    }

    private function serializeItem(ProductDTO $dto): array
    {
        return [
            'id'         => $dto->id,
            'name'       => $dto->name,
            'description' => $dto->description,
            'price'      => [
                'amount'   => $dto->priceAmount,
                'currency' => $dto->priceCurrency,
            ],
            'category'   => $dto->category,
            'brand'      => $dto->brand,
            'attributes' => $dto->attributes,
            'stock'      => $dto->stock,
            'imageUrl'   => $dto->imageUrl,
            'createdAt'  => $dto->createdAt,
            'updatedAt'  => $dto->updatedAt,
        ];
    }
}
