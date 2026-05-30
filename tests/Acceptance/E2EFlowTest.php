<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class E2EFlowTest extends WebTestCase
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
    // E2E01 — flujo de compra completo
    // ---------------------------------------------------------------

    public function testFullPurchaseFlowDecrementsStock(): void
    {
        // 1. Crear producto con stock=5
        $productId = $this->createProduct('Maillot E2E', 5);

        // 2. Crear carrito y añadir 2 unidades
        $this->postJson('/api/carts', []);
        $cartId = $this->responseBody()['cartId'];
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 2]);

        // 3. Verificar total del carrito
        $this->client->request('GET', "/api/carts/{$cartId}");
        self::assertSame(4999 * 2, $this->responseBody()['total']['amount']);

        // 4. Checkout con email
        $this->postJson('/api/orders', [
            'cartId'          => $cartId,
            'customerEmail'   => 'e2e@test.com',
            'shippingAddress' => ['street' => 'C/ Test 1', 'city' => 'Madrid', 'postalCode' => '28001', 'country' => 'ES'],
        ]);
        self::assertResponseStatusCodeSame(201);
        $orderId = $this->responseBody()['orderId'];

        // 5. Verificar pedido
        $this->client->request('GET', "/api/orders/{$orderId}");
        $order = $this->responseBody();
        self::assertSame('PENDING', $order['status']);
        self::assertCount(1, $order['lines']);
        self::assertSame(2, $order['lines'][0]['quantity']);
        self::assertSame(4999 * 2, $order['total']['amount']);

        // 6. Verificar stock decrementado: 5 - 2 = 3
        $this->client->request('GET', "/api/products/{$productId}");
        self::assertSame(3, $this->responseBody()['stock']);
    }

    // ---------------------------------------------------------------
    // E2E02 — compra y cancelación
    // ---------------------------------------------------------------

    public function testPurchaseAndCancelUpdatesStatus(): void
    {
        $productId = $this->createProduct('Culote E2E', 10);

        $this->postJson('/api/carts', []);
        $cartId = $this->responseBody()['cartId'];
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 1]);

        $this->postJson('/api/orders', [
            'cartId'          => $cartId,
            'shippingAddress' => ['street' => 'C/ Test 2', 'city' => 'Barcelona', 'postalCode' => '08001', 'country' => 'ES'],
        ]);
        $orderId = $this->responseBody()['orderId'];

        // Cancelar
        $this->client->request('POST', "/api/orders/{$orderId}/cancel");
        self::assertResponseStatusCodeSame(204);

        // Verificar cancelado
        $this->client->request('GET', "/api/orders/{$orderId}");
        self::assertSame('CANCELLED', $this->responseBody()['status']);

        // Segundo intento → 409
        $this->client->request('POST', "/api/orders/{$orderId}/cancel");
        self::assertResponseStatusCodeSame(409);
        self::assertSame('order_already_cancelled', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // E2E03 — modificar carrito antes del checkout
    // ---------------------------------------------------------------

    public function testModifyCartBeforeCheckout(): void
    {
        $productA = $this->createProduct('Producto A E2E', 10);
        $productB = $this->createProduct('Producto B E2E', 10);

        $this->postJson('/api/carts', []);
        $cartId = $this->responseBody()['cartId'];

        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productA, 'quantity' => 3]);
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productB, 'quantity' => 1]);

        // Reducir A a 1
        $this->client->request('PATCH', "/api/carts/{$cartId}/items/{$productA}",
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['quantity' => 1]),
        );
        // Eliminar B
        $this->client->request('DELETE', "/api/carts/{$cartId}/items/{$productB}");

        $this->client->request('GET', "/api/carts/{$cartId}");
        $cart = $this->responseBody();
        self::assertCount(1, $cart['items']);
        self::assertSame(1, $cart['items'][0]['quantity']);
        self::assertSame(4999, $cart['total']['amount']);

        // Checkout: 1 línea, total = precio × 1
        $this->postJson('/api/orders', [
            'cartId'          => $cartId,
            'shippingAddress' => ['street' => 'C/ Test 3', 'city' => 'Sevilla', 'postalCode' => '41001', 'country' => 'ES'],
        ]);
        self::assertResponseStatusCodeSame(201);
        $orderId = $this->responseBody()['orderId'];

        $this->client->request('GET', "/api/orders/{$orderId}");
        $order = $this->responseBody();
        self::assertCount(1, $order['lines']);
        self::assertSame(4999, $order['total']['amount']);
    }

    // ---------------------------------------------------------------
    // E2E04 — el precio se captura en el momento del add, no del checkout
    // ---------------------------------------------------------------

    public function testCartCapturesPriceAtAddTime(): void
    {
        $productId = $this->createProduct('Gafas E2E Price', 10);

        // Carrito 1: añadir al precio original (4999)
        $this->postJson('/api/carts', []);
        $cartId1 = $this->responseBody()['cartId'];
        $this->postJson("/api/carts/{$cartId1}/items", ['productId' => $productId, 'quantity' => 1]);

        // Actualizar precio del producto a 7999
        $this->client->request('PATCH', "/api/products/{$productId}", server: [
            'CONTENT_TYPE'       => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminKey,
        ], content: json_encode(['price' => ['amount' => 7999, 'currency' => 'EUR']]));
        self::assertResponseStatusCodeSame(204);

        // Carrito 1 conserva el precio original
        $this->client->request('GET', "/api/carts/{$cartId1}");
        self::assertSame(4999, $this->responseBody()['items'][0]['unitPrice']['amount']);

        // Carrito 2: captura el nuevo precio (7999)
        $this->postJson('/api/carts', []);
        $cartId2 = $this->responseBody()['cartId'];
        $this->postJson("/api/carts/{$cartId2}/items", ['productId' => $productId, 'quantity' => 1]);

        $this->client->request('GET', "/api/carts/{$cartId2}");
        self::assertSame(7999, $this->responseBody()['items'][0]['unitPrice']['amount']);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createProduct(string $name, int $stock): string
    {
        $this->postJson('/api/products', [
            'name'     => $name,
            'price'    => ['amount' => 4999, 'currency' => 'EUR'],
            'category' => 'CYCLING',
            'brand'    => 'Siroko',
            'stock'    => $stock,
        ], auth: true);

        return $this->responseBody()['productId'];
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
