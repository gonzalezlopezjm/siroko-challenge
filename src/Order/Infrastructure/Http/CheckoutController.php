<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http;

use App\Cart\Domain\Exception\CartNotFoundException;
use App\Order\Application\Command\CheckoutOrder\CheckoutOrderCommand;
use App\Order\Domain\Exception\CartIsEmptyException;
use App\Order\Domain\Exception\InsufficientStockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/orders', methods: ['POST'])]
final class CheckoutController
{
    public function __construct(private readonly MessageBusInterface $commandBus) {}

    public function __invoke(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);

        if (!is_array($body)) {
            return $this->error('invalid_request', 'Request body must be valid JSON.', Response::HTTP_BAD_REQUEST);
        }

        if (!isset($body['cartId'], $body['shippingAddress'])) {
            return $this->error('missing_field', 'Fields "cartId" and "shippingAddress" are required.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $address = $body['shippingAddress'];
        foreach (['street', 'city', 'postalCode', 'country'] as $field) {
            if (empty($address[$field])) {
                return $this->error('missing_field', sprintf('Field "shippingAddress.%s" is required.', $field), Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        try {
            $envelope = $this->commandBus->dispatch(new CheckoutOrderCommand(
                cartId: (string) $body['cartId'],
                customerId: isset($body['customerId']) ? (string) $body['customerId'] : null,
                customerEmail: isset($body['customerEmail']) ? (string) $body['customerEmail'] : null,
                shippingStreet: (string) $address['street'],
                shippingCity: (string) $address['city'],
                shippingPostalCode: (string) $address['postalCode'],
                shippingCountry: (string) $address['country'],
            ));

            $orderId = $envelope->last(HandledStamp::class)->getResult();

            return new JsonResponse(['orderId' => $orderId], Response::HTTP_CREATED);
        } catch (HandlerFailedException $e) {
            return $this->handleDomainException($e->getPrevious() ?? $e);
        } catch (\InvalidArgumentException $e) {
            return $this->handleDomainException($e);
        }
    }

    private function handleDomainException(\Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof CartNotFoundException    => $this->error('cart_not_found', $e->getMessage(), Response::HTTP_NOT_FOUND),
            $e instanceof CartIsEmptyException     => $this->error('cart_is_empty', $e->getMessage(), Response::HTTP_UNPROCESSABLE_ENTITY),
            $e instanceof InsufficientStockException => new JsonResponse(['error' => ['code' => 'insufficient_stock', 'message' => $e->getMessage(), 'context' => ['productId' => $e->productId(), 'available' => $e->available(), 'requested' => $e->requested()]]], Response::HTTP_UNPROCESSABLE_ENTITY),
            $e instanceof \InvalidArgumentException => $this->error('invalid_id', $e->getMessage(), Response::HTTP_BAD_REQUEST),
            default => throw $e,
        };
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => ['code' => $code, 'message' => $message]], $status);
    }
}
