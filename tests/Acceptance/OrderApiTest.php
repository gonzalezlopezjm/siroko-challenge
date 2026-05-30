<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OrderApiTest extends WebTestCase
{
    private KernelBrowser $client;

    private string $adminKey;

    protected function setUp(): void
    {
        $this->client   = self::createClient(['environment' => 'test', 'debug' => true]);
        $this->adminKey = self::getContainer()->getParameter('admin_api_key');

        $em   = self::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropDatabase();
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());
    }

    // ---------------------------------------------------------------
    // POST /api/orders — Checkout — happy path
    // ---------------------------------------------------------------

    /** ORD01 — checkout mínimo sin customerId ni email */
    public function testCheckoutSucceeds(): void
    {
        $cartId = $this->createCartWithProduct(2);

        $this->postJson('/api/orders', $this->checkoutPayload($cartId));

        self::assertResponseStatusCodeSame(201);
        $body = $this->responseBody();
        self::assertArrayHasKey('orderId', $body);
        self::assertNotEmpty($body['orderId']);
    }

    /** ORD01 — customerId es null cuando no se envía */
    public function testCheckoutWithoutCustomerIdHasNullCustomerId(): void
    {
        $cartId  = $this->createCartWithProduct(1);
        $this->postJson('/api/orders', $this->checkoutPayload($cartId));
        $orderId = $this->responseBody()['orderId'];

        $this->client->request('GET', "/api/orders/{$orderId}");
        self::assertNull($this->responseBody()['customerId']);
    }

    /** ORD01 — checkout decrementa el stock del producto */
    public function testCheckoutDecrementsProductStock(): void
    {
        $productId = $this->createProductWithStock(5);
        $cartId    = $this->createCartWithItem($productId, 2);

        $this->postJson('/api/orders', $this->checkoutPayload($cartId));
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', "/api/products/{$productId}");
        self::assertSame(3, $this->responseBody()['stock']);
    }

    /** ORD02 — checkout con customerId y customerEmail */
    public function testCheckoutWithCustomerIdAndEmailSucceeds(): void
    {
        $cartId  = $this->createCartWithProduct(1);
        $payload = $this->checkoutPayload($cartId, 'user-42');
        $payload['customerEmail'] = 'cliente@ejemplo.com';

        $this->postJson('/api/orders', $payload);

        self::assertResponseStatusCodeSame(201);
        $orderId = $this->responseBody()['orderId'];

        $this->client->request('GET', "/api/orders/{$orderId}");
        $body = $this->responseBody();
        self::assertSame('user-42', $body['customerId']);
        self::assertSame('PENDING', $body['status']);
    }

    /** ORD03 — checkout con carrito de múltiples ítems: multi-línea y total correcto */
    public function testCheckoutWithMultipleItemsCreatesMultiLineOrder(): void
    {
        $productA = $this->createProductWithStock(10);
        $productB = $this->createProductWithStock(10);
        $productC = $this->createProductWithStock(10);
        $cartId   = $this->createCart();

        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productA, 'quantity' => 1]);
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productB, 'quantity' => 2]);
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productC, 'quantity' => 3]);

        $this->postJson('/api/orders', $this->checkoutPayload($cartId));

        self::assertResponseStatusCodeSame(201);
        $orderId = $this->responseBody()['orderId'];

        $this->client->request('GET', "/api/orders/{$orderId}");
        $body = $this->responseBody();
        self::assertCount(3, $body['lines']);
        self::assertSame(4999 * (1 + 2 + 3), $body['total']['amount']);
    }

    /** ORD04 — el carrito persiste después del checkout */
    public function testCartPersistsAfterCheckout(): void
    {
        $cartId = $this->createCartWithProduct(1);

        $this->postJson('/api/orders', $this->checkoutPayload($cartId));
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', "/api/carts/{$cartId}");
        self::assertResponseStatusCodeSame(200);
        self::assertNotEmpty($this->responseBody()['items']);
    }

    // ---------------------------------------------------------------
    // POST /api/orders — Checkout — errores de validación
    // ---------------------------------------------------------------

    /** ORD05 — cartId ausente → 422 */
    public function testCheckoutMissingFieldsReturns422(): void
    {
        $this->postJson('/api/orders', ['cartId' => '550e8400-e29b-41d4-a716-446655440000']);

        self::assertResponseStatusCodeSame(422);
    }

    /** ORD06 — campo de shippingAddress ausente → 422 */
    public function testCheckoutMissingShippingAddressFieldReturns422(): void
    {
        $cartId = $this->createCartWithProduct(1);

        foreach (['street', 'city', 'postalCode', 'country'] as $field) {
            $payload = $this->checkoutPayload($cartId);
            unset($payload['shippingAddress'][$field]);

            $this->postJson('/api/orders', $payload);

            self::assertResponseStatusCodeSame(422, "Esperaba 422 al omitir shippingAddress.{$field}");
            self::assertSame('missing_field', $this->responseBody()['error']['code']);
        }
    }

    /** ORD07 — cartId con formato inválido → 400 */
    public function testCheckoutInvalidCartIdFormatReturns400(): void
    {
        $this->postJson('/api/orders', [
            'cartId'          => 'no-es-un-uuid',
            'shippingAddress' => [
                'street'     => 'Calle Mayor 1',
                'city'       => 'Madrid',
                'postalCode' => '28001',
                'country'    => 'ES',
            ],
        ]);

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_id', $this->responseBody()['error']['code']);
    }

    /** ORD08 — cartId válido pero inexistente → 404 */
    public function testCheckoutCartNotFoundReturns404(): void
    {
        $this->postJson('/api/orders', $this->checkoutPayload('550e8400-e29b-41d4-a716-446655440000'));

        self::assertResponseStatusCodeSame(404);
        self::assertSame('cart_not_found', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // POST /api/orders — Checkout — reglas de negocio
    // ---------------------------------------------------------------

    /** ORD09 — carrito vacío → 422 */
    public function testCheckoutEmptyCartReturns422(): void
    {
        $this->postJson('/api/carts', []);
        $cartId = $this->responseBody()['cartId'];

        $this->postJson('/api/orders', $this->checkoutPayload($cartId));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('cart_is_empty', $this->responseBody()['error']['code']);
    }

    /** ORD10 — stock insuficiente en checkout → 422 + stock no cambia */
    public function testCheckoutInsufficientStockReturns422(): void
    {
        $productId = $this->createProductWithStock(1);
        $cartId1   = $this->createCartWithItem($productId, 1);
        $cartId2   = $this->createCartWithItem($productId, 1);

        $this->postJson('/api/orders', $this->checkoutPayload($cartId1));
        self::assertResponseStatusCodeSame(201);

        $this->postJson('/api/orders', $this->checkoutPayload($cartId2));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('insufficient_stock', $this->responseBody()['error']['code']);
    }

    /** ORD10 — la transacción es atómica: stock no cambia si falla */
    public function testCheckoutFailureDoesNotDecrementStock(): void
    {
        $productId = $this->createProductWithStock(2);
        $cartId    = $this->createCartWithItem($productId, 3);

        $this->postJson('/api/orders', $this->checkoutPayload($cartId));
        self::assertResponseStatusCodeSame(422);

        $this->client->request('GET', "/api/products/{$productId}");
        self::assertSame(2, $this->responseBody()['stock']);
    }

    // ---------------------------------------------------------------
    // GET /api/orders/{orderId}
    // ---------------------------------------------------------------

    /** ORD15 — detalle del pedido: estructura completa */
    public function testGetOrderReturnsCorrectData(): void
    {
        $cartId  = $this->createCartWithProduct(2);
        $this->postJson('/api/orders', $this->checkoutPayload($cartId));
        $orderId = $this->responseBody()['orderId'];

        $this->client->request('GET', "/api/orders/{$orderId}");

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame($orderId, $body['id']);
        self::assertSame('PENDING', $body['status']);
        self::assertCount(1, $body['lines']);
        self::assertSame(2, $body['lines'][0]['quantity']);
        self::assertSame(4999, $body['lines'][0]['unitPrice']['amount']);
        self::assertSame(4999 * 2, $body['lines'][0]['subtotal']['amount']);
        self::assertSame(4999 * 2, $body['total']['amount']);
        self::assertSame('EUR', $body['total']['currency']);
        self::assertSame('Calle Mayor 12', $body['shippingAddress']['street']);
        self::assertSame('Madrid', $body['shippingAddress']['city']);
        self::assertSame('28001', $body['shippingAddress']['postalCode']);
        self::assertSame('ES', $body['shippingAddress']['country']);
        self::assertArrayHasKey('createdAt', $body);
    }

    /** ORD16 — pedido inexistente → 404 */
    public function testGetOrderNotFoundReturns404(): void
    {
        $this->client->request('GET', '/api/orders/550e8400-e29b-41d4-a716-446655440000');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('order_not_found', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // POST /api/orders/{orderId}/cancel
    // ---------------------------------------------------------------

    /** ORD12 — cancelar pedido PENDING → CANCELLED */
    public function testCancelOrderSucceeds(): void
    {
        $cartId  = $this->createCartWithProduct(1);
        $this->postJson('/api/orders', $this->checkoutPayload($cartId));
        $orderId = $this->responseBody()['orderId'];

        $this->client->request('POST', "/api/orders/{$orderId}/cancel");

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/orders/{$orderId}");
        self::assertSame('CANCELLED', $this->responseBody()['status']);
    }

    /** ORD13 — cancelar pedido ya cancelado → 409 */
    public function testCancelAlreadyCancelledOrderReturns409(): void
    {
        $cartId  = $this->createCartWithProduct(1);
        $this->postJson('/api/orders', $this->checkoutPayload($cartId));
        $orderId = $this->responseBody()['orderId'];

        $this->client->request('POST', "/api/orders/{$orderId}/cancel");
        $this->client->request('POST', "/api/orders/{$orderId}/cancel");

        self::assertResponseStatusCodeSame(409);
        self::assertSame('order_already_cancelled', $this->responseBody()['error']['code']);
    }

    /** ORD14 — cancelar pedido inexistente → 404 */
    public function testCancelOrderNotFoundReturns404(): void
    {
        $this->client->request('POST', '/api/orders/550e8400-e29b-41d4-a716-446655440000/cancel');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('order_not_found', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createCartWithProduct(int $quantity = 1): string
    {
        $productId = $this->createProductWithStock(10);

        return $this->createCartWithItem($productId, $quantity);
    }

    private function createCartWithItem(string $productId, int $quantity): string
    {
        $cartId = $this->createCart();
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => $quantity]);

        return $cartId;
    }

    private function createCart(): string
    {
        $this->postJson('/api/carts', []);

        return $this->responseBody()['cartId'];
    }

    private function createProductWithStock(int $stock): string
    {
        static $counter = 0;
        ++$counter;

        $this->postJson('/api/products', [
            'name'     => "Producto Test {$counter}",
            'price'    => ['amount' => 4999, 'currency' => 'EUR'],
            'category' => 'CYCLING',
            'brand'    => 'Mizuno',
            'stock'    => $stock,
        ], auth: true);

        return $this->responseBody()['productId'];
    }

    private function checkoutPayload(string $cartId, ?string $customerId = null): array
    {
        return [
            'cartId'          => $cartId,
            'customerId'      => $customerId,
            'shippingAddress' => [
                'street'     => 'Calle Mayor 12',
                'city'       => 'Madrid',
                'postalCode' => '28001',
                'country'    => 'ES',
            ],
        ];
    }

    private function postJson(string $uri, array $body, bool $auth = false): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($auth) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $this->adminKey;
        }

        $this->client->request('POST', $uri, server: $server, content: json_encode($body));
    }

    private function responseBody(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
