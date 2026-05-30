<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CartApiTest extends WebTestCase
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
    // POST /api/carts — Create
    // ---------------------------------------------------------------

    /** CART01 — carrito anónimo */
    public function testCreateCartReturnsId(): void
    {
        $this->postJson('/api/carts', []);

        self::assertResponseStatusCodeSame(201);
        $body = $this->responseBody();
        self::assertArrayHasKey('cartId', $body);
        self::assertNotEmpty($body['cartId']);
    }

    /** CART02 — carrito con customerId persiste el campo */
    public function testCreateCartWithCustomerIdPersistsIt(): void
    {
        $this->postJson('/api/carts', ['customerId' => 'user-123']);

        self::assertResponseStatusCodeSame(201);
        $cartId = $this->responseBody()['cartId'];

        $this->client->request('GET', "/api/carts/{$cartId}");
        self::assertSame('user-123', $this->responseBody()['customerId']);
    }

    // ---------------------------------------------------------------
    // GET /api/carts/{cartId}
    // ---------------------------------------------------------------

    /** CART03 — carrito vacío recién creado */
    public function testGetCartReturnsEmptyCart(): void
    {
        $cartId = $this->createCart();

        $this->client->request('GET', "/api/carts/{$cartId}");

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame($cartId, $body['id']);
        self::assertNull($body['customerId']);
        self::assertEmpty($body['items']);
        self::assertSame(0, $body['total']['amount']);
        self::assertSame('EUR', $body['total']['currency']);
    }

    /** CART04 — carrito inexistente → 404 */
    public function testGetCartNotFoundReturns404(): void
    {
        $this->client->request('GET', '/api/carts/550e8400-e29b-41d4-a716-446655440000');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('cart_not_found', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // POST /api/carts/{cartId}/items — Add item
    // ---------------------------------------------------------------

    /** CART05 — añadir un ítem: quantity, unitPrice y subtotal correctos */
    public function testAddItemToCartSucceeds(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();

        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 2]);

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/carts/{$cartId}");
        $body = $this->responseBody();
        self::assertCount(1, $body['items']);
        self::assertSame($productId, $body['items'][0]['productId']);
        self::assertSame(2, $body['items'][0]['quantity']);
        self::assertSame(4999, $body['items'][0]['unitPrice']['amount']);
        self::assertSame(4999 * 2, $body['items'][0]['subtotal']['amount']);
        self::assertSame(4999 * 2, $body['total']['amount']);
    }

    /** CART06 — añadir el mismo producto dos veces acumula cantidad */
    public function testAddSameProductTwiceAccumulatesQuantity(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();

        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 3]);
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 4]);

        $this->client->request('GET', "/api/carts/{$cartId}");
        $body = $this->responseBody();

        self::assertCount(1, $body['items']);
        self::assertSame(7, $body['items'][0]['quantity']);
    }

    /** CART07 — múltiples productos distintos: total correcto */
    public function testAddMultipleDistinctProductsComputesTotalCorrectly(): void
    {
        $productA = $this->createProduct();
        $productB = $this->createProduct();
        $cartId   = $this->createCart();

        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productA, 'quantity' => 1]);
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productB, 'quantity' => 2]);

        $this->client->request('GET', "/api/carts/{$cartId}");
        $body = $this->responseBody();

        self::assertCount(2, $body['items']);
        self::assertSame(4999 * 3, $body['total']['amount']);
    }

    /** CART08 — acumulación de cantidad se limita a 99 silenciosamente */
    public function testAddItemQuantityCappedAt99(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();

        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 95]);
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 10]);

        $this->client->request('GET', "/api/carts/{$cartId}");
        self::assertSame(99, $this->responseBody()['items'][0]['quantity']);
    }

    // ---------------------------------------------------------------
    // POST /api/carts/{cartId}/items — Errores
    // ---------------------------------------------------------------

    /** CART09 — carrito inexistente → 404 */
    public function testAddItemCartNotFoundReturns404(): void
    {
        $productId = $this->createProduct();

        $this->postJson('/api/carts/550e8400-e29b-41d4-a716-446655440000/items', ['productId' => $productId, 'quantity' => 1]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('cart_not_found', $this->responseBody()['error']['code']);
    }

    /** CART10 — producto inexistente → 404 */
    public function testAddItemProductNotFoundReturns404(): void
    {
        $cartId = $this->createCart();

        $this->postJson("/api/carts/{$cartId}/items", [
            'productId' => '550e8400-e29b-41d4-a716-446655440000',
            'quantity'  => 1,
        ]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('product_not_found', $this->responseBody()['error']['code']);
    }

    /** CART11 — producto sin stock → 422 */
    public function testAddItemOutOfStockProductReturns422(): void
    {
        $cartId    = $this->createCart();
        $productId = $this->createProductWithStock(0);

        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 1]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('insufficient_stock', $this->responseBody()['error']['code']);
    }

    /** CART12 — falta productId → 422 */
    public function testAddItemMissingProductIdReturns422(): void
    {
        $cartId = $this->createCart();

        $this->postJson("/api/carts/{$cartId}/items", ['quantity' => 1]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('missing_field', $this->responseBody()['error']['code']);
    }

    /** CART12 — falta quantity → 422 */
    public function testAddItemMissingQuantityReturns422(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();

        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('missing_field', $this->responseBody()['error']['code']);
    }

    /** CART13 — límite de 50 líneas distintas */
    public function testAddItemExceeds50LinesLimitReturns422(): void
    {
        $cartId = $this->createCart();

        for ($i = 0; $i < 50; $i++) {
            $productId = $this->createProductWithStock(10, "Producto Limite {$i}");
            $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 1]);
            self::assertResponseStatusCodeSame(204, "Fallo al añadir el producto {$i}");
        }

        $extra = $this->createProductWithStock(10, 'Producto Extra 51');
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $extra, 'quantity' => 1]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('cart_lines_limit_exceeded', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // PATCH /api/carts/{cartId}/items/{productId}
    // ---------------------------------------------------------------

    /** CART14 — actualizar cantidad correctamente */
    public function testUpdateItemQuantitySucceeds(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 1]);

        $this->patchJson("/api/carts/{$cartId}/items/{$productId}", ['quantity' => 5]);

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/carts/{$cartId}");
        $body = $this->responseBody();
        self::assertSame(5, $body['items'][0]['quantity']);
        self::assertSame(4999 * 5, $body['total']['amount']);
    }

    /** CART15 — actualizar a 0 mantiene la línea (no la elimina) */
    public function testUpdateItemQuantityToZeroKeepsLine(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 3]);

        $this->patchJson("/api/carts/{$cartId}/items/{$productId}", ['quantity' => 0]);

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/carts/{$cartId}");
        $body = $this->responseBody();
        self::assertCount(1, $body['items']);
        self::assertSame(0, $body['items'][0]['quantity']);
    }

    /** CART16 — actualizar ítem que no está en el carrito → 404 */
    public function testUpdateItemNotInCartReturns404(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();

        $this->patchJson("/api/carts/{$cartId}/items/{$productId}", ['quantity' => 5]);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('item_not_found', $this->responseBody()['error']['code']);
    }

    /** CART17 — cantidad > 99 queda limitada a 99 silenciosamente */
    public function testUpdateItemQuantityCappedAt99(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 1]);

        $this->patchJson("/api/carts/{$cartId}/items/{$productId}", ['quantity' => 150]);

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/carts/{$cartId}");
        self::assertSame(99, $this->responseBody()['items'][0]['quantity']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/carts/{cartId}/items/{productId} — Remove item
    // ---------------------------------------------------------------

    /** CART18 — eliminar un ítem */
    public function testRemoveItemFromCartSucceeds(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 1]);

        $this->client->request('DELETE', "/api/carts/{$cartId}/items/{$productId}");

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/carts/{$cartId}");
        self::assertEmpty($this->responseBody()['items']);
        self::assertSame(0, $this->responseBody()['total']['amount']);
    }

    /** CART19 — eliminar ítem que no existe en el carrito → 404 */
    public function testRemoveItemNotInCartReturns404(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();

        $this->client->request('DELETE', "/api/carts/{$cartId}/items/{$productId}");

        self::assertResponseStatusCodeSame(404);
        self::assertSame('item_not_found', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/carts/{cartId}/items — Clear
    // ---------------------------------------------------------------

    /** CART20 — vaciar carrito */
    public function testClearCartSucceeds(): void
    {
        $productId = $this->createProduct();
        $cartId    = $this->createCart();
        $this->postJson("/api/carts/{$cartId}/items", ['productId' => $productId, 'quantity' => 3]);

        $this->client->request('DELETE', "/api/carts/{$cartId}/items");

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/carts/{$cartId}");
        $body = $this->responseBody();
        self::assertEmpty($body['items']);
        self::assertSame(0, $body['total']['amount']);
    }

    /** CART21 — vaciar carrito inexistente → 404 */
    public function testClearInexistentCartReturns404(): void
    {
        $this->client->request('DELETE', '/api/carts/550e8400-e29b-41d4-a716-446655440000/items');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('cart_not_found', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createCart(): string
    {
        $this->postJson('/api/carts', []);

        return $this->responseBody()['cartId'];
    }

    private function createProduct(): string
    {
        return $this->createProductWithStock(10);
    }

    private function createProductWithStock(int $stock, string $name = ''): string
    {
        static $counter = 0;
        ++$counter;
        $name = $name ?: "Producto Cart Test {$counter}";

        $this->postJson('/api/products', [
            'name'     => $name,
            'price'    => ['amount' => 4999, 'currency' => 'EUR'],
            'category' => 'CYCLING',
            'brand'    => 'Mizuno',
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

    private function patchJson(string $uri, array $body): void
    {
        $this->client->request('PATCH', $uri, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode($body));
    }

    private function responseBody(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
