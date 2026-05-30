<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Http;

use App\Search\Application\Query\SearchResponseDTO;
use App\Search\Application\Query\SearchResultDTO;
use App\Search\Application\Query\SearchProducts\SearchProductsQuery;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/search', methods: ['GET'])]
final class SearchController
{
    public function __construct(private readonly MessageBusInterface $queryBus) {}

    public function __invoke(Request $request): JsonResponse
    {
        $q     = (string) $request->query->get('q', '');
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));

        if (trim($q) === '') {
            return new JsonResponse(
                ['error' => ['code' => 'invalid_search_query', 'message' => 'Search query cannot be empty.']],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        try {
            $envelope = $this->queryBus->dispatch(new SearchProductsQuery($q, $limit));
            /** @var SearchResponseDTO $dto */
            $dto = $envelope->last(HandledStamp::class)->getResult();

            return new JsonResponse($this->serialize($dto));
        } catch (HandlerFailedException $e) {
            throw $e->getPrevious() ?? $e;
        }
    }

    private function serialize(SearchResponseDTO $dto): array
    {
        $results = array_map(function (SearchResultDTO $r): array {
            $p    = $r->product;
            $item = [
                'id'          => $p->id,
                'name'        => $p->name,
                'description' => $p->description,
                'price'       => ['amount' => $p->priceAmount, 'currency' => $p->priceCurrency],
                'category'    => $p->category,
                'brand'       => $p->brand,
                'attributes'  => $p->attributes,
                'stock'       => $p->stock,
                'imageUrl'    => $p->imageUrl,
                'createdAt'   => $p->createdAt,
                'updatedAt'   => $p->updatedAt,
            ];
            if ($r->score !== null) {
                $item['score'] = $r->score;
            }
            return $item;
        }, $dto->results);

        return [
            'results' => $results,
            'meta'    => ['results_count' => $dto->resultsCount],
        ];
    }
}
