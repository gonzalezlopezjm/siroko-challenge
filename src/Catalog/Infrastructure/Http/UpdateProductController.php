<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Http;

use App\Catalog\Application\Command\UpdateProduct\UpdateProductCommand;
use App\Catalog\Domain\Exception\DuplicateProductNameException;
use App\Catalog\Domain\Exception\InvalidPriceException;
use App\Catalog\Domain\Exception\InvalidStockException;
use App\Catalog\Domain\Exception\ProductNotFoundException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/products/{id}', methods: ['PATCH'])]
final class UpdateProductController
{
    public function __construct(private readonly MessageBusInterface $commandBus) {}

    public function __invoke(Request $request, string $id): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return $this->error('invalid_request', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        // Parse nested price object if provided
        $priceAmount   = null;
        $priceCurrency = null;
        if (array_key_exists('price', $body)) {
            $price = $body['price'];
            if (!is_array($price) || !isset($price['amount'], $price['currency'])) {
                return $this->error('invalid_price', 'Field "price" must be an object with "amount" (int) and "currency" (string).', Response::HTTP_UNPROCESSABLE_ENTITY);
            }
            $priceAmount   = (int) $price['amount'];
            $priceCurrency = (string) $price['currency'];
        }

        try {
            $this->commandBus->dispatch(new UpdateProductCommand(
                productId: $id,
                name: isset($body['name']) ? (string) $body['name'] : null,
                description: isset($body['description']) ? (string) $body['description'] : null,
                priceAmount: $priceAmount,
                priceCurrency: $priceCurrency,
                category: isset($body['category']) ? (string) $body['category'] : null,
                brand: isset($body['brand']) ? (string) $body['brand'] : null,
                attributes: isset($body['attributes']) && is_array($body['attributes']) ? $body['attributes'] : null,
                stock: isset($body['stock']) ? (int) $body['stock'] : null,
                imageUrl: array_key_exists('imageUrl', $body) && $body['imageUrl'] !== null ? (string) $body['imageUrl'] : null,
                clearImageUrl: array_key_exists('imageUrl', $body) && $body['imageUrl'] === null,
            ));

            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        } catch (HandlerFailedException $e) {
            return $this->handleDomainException($e->getPrevious() ?? $e);
        } catch (\ValueError | \InvalidArgumentException $e) {
            return $this->handleDomainException($e);
        }
    }

    private function handleDomainException(\Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ProductNotFoundException      => $this->error('product_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND),
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
