<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\Command\CreateProduct\CreateProductCommand;
use App\Catalog\Domain\Exception\DuplicateProductNameException;
use App\Catalog\Domain\Exception\InvalidPriceException;
use App\Catalog\Domain\Exception\InvalidStockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/products', methods: ['POST'])]
final class CreateProductController
{
    public function __construct(private readonly MessageBusInterface $commandBus) {}

    public function __invoke(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return $this->error('invalid_request', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        foreach (['name', 'price', 'category', 'brand', 'stock'] as $field) {
            if (!array_key_exists($field, $body)) {
                return $this->error('missing_field', sprintf('Field "%s" is required.', $field), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        $price = $body['price'] ?? [];
        if (!is_array($price) || !isset($price['amount'], $price['currency'])) {
            return $this->error('invalid_price', 'Field "price" must be an object with "amount" (int) and "currency" (string).', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $envelope = $this->commandBus->dispatch(new CreateProductCommand(
                name: (string) $body['name'],
                description: (string) ($body['description'] ?? ''),
                priceAmount: (int) $price['amount'],
                priceCurrency: (string) $price['currency'],
                category: (string) $body['category'],
                brand: (string) $body['brand'],
                attributes: is_array($body['attributes'] ?? null) ? $body['attributes'] : [],
                stock: (int) $body['stock'],
                imageUrl: isset($body['imageUrl']) ? (string) $body['imageUrl'] : null,
            ));

            $productId = $envelope->last(HandledStamp::class)->getResult();

            return new JsonResponse(['productId' => $productId], Response::HTTP_CREATED);
        } catch (HandlerFailedException $e) {
            return $this->handleDomainException($e->getPrevious() ?? $e);
        } catch (\ValueError | \InvalidArgumentException $e) {
            return $this->handleDomainException($e);
        }
    }

    private function handleDomainException(\Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof DuplicateProductNameException => $this->error('duplicate_product_name', $e->getMessage(), Response::HTTP_CONFLICT),
            $e instanceof InvalidPriceException         => $this->error('invalid_price', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY),
            $e instanceof InvalidStockException         => $this->error('invalid_stock', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY),
            $e instanceof \ValueError                   => $this->error('invalid_enum_value', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY),
            $e instanceof \InvalidArgumentException     => $this->error('validation_error', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY),
            default                                     => throw $e,
        };
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
