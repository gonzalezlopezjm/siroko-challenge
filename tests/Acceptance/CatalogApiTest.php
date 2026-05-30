<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CatalogApiTest extends WebTestCase
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
    // POST /api/products — Create — happy path
    // ---------------------------------------------------------------

    /** CAT01 — producto mínimo válido */
    public function testCreateProductSucceeds(): void
    {
        $this->postJson('/api/products', $this->validProductPayload(), auth: true);

        self::assertResponseStatusCodeSame(201);
        $body = $this->responseBody();
        self::assertArrayHasKey('productId', $body);
        self::assertNotEmpty($body['productId']);
    }

    /** CAT01 — el GET posterior devuelve defaults correctos */
    public function testCreateProductMinimalDefaultsAreCorrect(): void
    {
        $payload = $this->validProductPayload();
        unset($payload['description'], $payload['attributes'], $payload['imageUrl']);

        $this->postJson('/api/products', $payload, auth: true);
        $productId = $this->responseBody()['productId'];

        $this->client->request('GET', "/api/products/{$productId}");
        $body = $this->responseBody();
        self::assertSame('', $body['description']);
        self::assertSame([], $body['attributes']);
        self::assertNull($body['imageUrl']);
    }

    /** CAT02 — producto completo con atributos e imageUrl */
    public function testCreateProductWithAllFieldsSucceeds(): void
    {
        $this->postJson('/api/products', [
            'name'        => 'Culote Gravel Test',
            'description' => 'Culote para gravel con badana integrada.',
            'price'       => ['amount' => 8995, 'currency' => 'EUR'],
            'category'    => 'CYCLING',
            'brand'       => 'Siroko',
            'attributes'  => ['color' => ['negro', 'tierra'], 'talla' => ['S', 'M', 'L'], 'genero' => ['hombre']],
            'stock'       => 25,
            'imageUrl'    => 'https://example.com/culote.jpg',
        ], auth: true);

        self::assertResponseStatusCodeSame(201);
        $productId = $this->responseBody()['productId'];

        $this->client->request('GET', "/api/products/{$productId}");
        $body = $this->responseBody();
        self::assertSame(['negro', 'tierra'], $body['attributes']['color']);
        self::assertSame('https://example.com/culote.jpg', $body['imageUrl']);
        self::assertSame('Culote para gravel con badana integrada.', $body['description']);
    }

    /** CAT03 — todos los valores del enum Category son aceptados */
    #[DataProvider('categoryEnumProvider')]
    public function testCreateProductAllCategoryEnumsAreValid(string $category): void
    {
        $payload             = $this->validProductPayload();
        $payload['name']     = "Producto {$category}";
        $payload['category'] = $category;

        $this->postJson('/api/products', $payload, auth: true);

        self::assertResponseStatusCodeSame(201);

        $productId = $this->responseBody()['productId'];
        $this->client->request('GET', "/api/products/{$productId}");
        self::assertSame($category, $this->responseBody()['category']);
    }

    public static function categoryEnumProvider(): array
    {
        return [
            ['CYCLING'],
            ['FITNESS'],
            ['APPAREL'],
            ['ACCESSORIES'],
        ];
    }

    // ---------------------------------------------------------------
    // POST /api/products — Create — validaciones de entrada
    // ---------------------------------------------------------------

    /** CAT04 — cada campo requerido provoca 422 si falta */
    #[DataProvider('requiredFieldsProvider')]
    public function testCreateProductMissingRequiredFieldReturns422(string $field): void
    {
        $payload = $this->validProductPayload();
        unset($payload[$field]);

        $this->postJson('/api/products', $payload, auth: true);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('missing_field', $this->responseBody()['error']['code']);
    }

    public static function requiredFieldsProvider(): array
    {
        return [
            ['name'],
            ['price'],
            ['category'],
            ['brand'],
            ['stock'],
        ];
    }

    /** CAT05 — body vacío / JSON inválido → 400 */
    public function testCreateProductInvalidJsonReturns400(): void
    {
        $this->client->request('POST', '/api/products', server: [
            'CONTENT_TYPE'       => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminKey,
        ], content: 'not valid json');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_request', $this->responseBody()['error']['code']);
    }

    /** CAT06 — precio = 0 → 422 */
    public function testCreateProductInvalidPriceReturnsUnprocessable(): void
    {
        $payload          = $this->validProductPayload();
        $payload['price'] = ['amount' => 0, 'currency' => 'EUR'];

        $this->postJson('/api/products', $payload, auth: true);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_price', $this->responseBody()['error']['code']);
    }

    /** CAT06 — precio negativo → 422 */
    public function testCreateProductNegativePriceReturns422(): void
    {
        $payload          = $this->validProductPayload();
        $payload['price'] = ['amount' => -1, 'currency' => 'EUR'];

        $this->postJson('/api/products', $payload, auth: true);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_price', $this->responseBody()['error']['code']);
    }

    /** CAT07 — price no es objeto → 422 */
    public function testCreateProductPriceNotObjectReturns422(): void
    {
        $payload          = $this->validProductPayload();
        $payload['price'] = 4995;

        $this->postJson('/api/products', $payload, auth: true);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_price', $this->responseBody()['error']['code']);
    }

    /** CAT07 — price sin currency → 422 */
    public function testCreateProductPriceMissingCurrencyReturns422(): void
    {
        $payload          = $this->validProductPayload();
        $payload['price'] = ['amount' => 4995];

        $this->postJson('/api/products', $payload, auth: true);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_price', $this->responseBody()['error']['code']);
    }

    /** CAT08 — stock negativo → 422 */
    public function testCreateProductNegativeStockReturns422(): void
    {
        $payload          = $this->validProductPayload();
        $payload['stock'] = -1;

        $this->postJson('/api/products', $payload, auth: true);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_stock', $this->responseBody()['error']['code']);
    }

    /** CAT08 — stock = 0 es válido (producto agotado) */
    public function testCreateProductStockZeroIsValid(): void
    {
        $payload          = $this->validProductPayload();
        $payload['stock'] = 0;

        $this->postJson('/api/products', $payload, auth: true);

        self::assertResponseStatusCodeSame(201);

        $productId = $this->responseBody()['productId'];
        $this->client->request('GET', "/api/products/{$productId}");
        self::assertSame(0, $this->responseBody()['stock']);
    }

    /** CAT09 — categoría fuera del enum → 422 */
    public function testCreateProductInvalidCategoryReturns422(): void
    {
        $payload             = $this->validProductPayload();
        $payload['category'] = 'RUNNING';

        $this->postJson('/api/products', $payload, auth: true);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_enum_value', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // POST /api/products — Create — reglas de negocio
    // ---------------------------------------------------------------

    /** CAT10 — nombre duplicado en la misma categoría → 409 */
    public function testCreateProductDuplicateNameAndCategoryReturnsConflict(): void
    {
        $this->postJson('/api/products', $this->validProductPayload(), auth: true);
        $this->postJson('/api/products', $this->validProductPayload(), auth: true);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('duplicate_product_name', $this->responseBody()['error']['code']);
    }

    /** CAT11 — mismo nombre en categoría distinta está permitido */
    public function testSameNameDifferentCategoryIsAllowed(): void
    {
        $payload             = $this->validProductPayload();
        $payload['name']     = 'Camiseta Basic';
        $payload['category'] = 'FITNESS';
        $this->postJson('/api/products', $payload, auth: true);
        $idA = $this->responseBody()['productId'];

        $payload['category'] = 'APPAREL';
        $this->postJson('/api/products', $payload, auth: true);
        $idB = $this->responseBody()['productId'];

        self::assertResponseStatusCodeSame(201);
        self::assertNotSame($idA, $idB);
    }

    // ---------------------------------------------------------------
    // PATCH /api/products/{id} — Update
    // ---------------------------------------------------------------

    /** CAT12 — actualizar un solo campo (precio) */
    public function testUpdateProductSucceeds(): void
    {
        $productId = $this->createProduct();

        $this->patchJson("/api/products/{$productId}", [
            'name'  => 'Mallot Actualizado',
            'price' => ['amount' => 5999, 'currency' => 'EUR'],
        ], auth: true);

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/products/{$productId}");
        $body = $this->responseBody();
        self::assertSame('Mallot Actualizado', $body['name']);
        self::assertSame(5999, $body['price']['amount']);
    }

    /** CAT12 — los campos no enviados no cambian */
    public function testUpdateProductOnlyPatchesSentFields(): void
    {
        $productId = $this->createProduct();

        $this->patchJson("/api/products/{$productId}", ['price' => ['amount' => 5999, 'currency' => 'EUR']], auth: true);

        $this->client->request('GET', "/api/products/{$productId}");
        $body = $this->responseBody();
        self::assertSame('Mallot Mizuno', $body['name']);
        self::assertSame(10, $body['stock']);
        self::assertSame(5999, $body['price']['amount']);
    }

    /** CAT13 — actualizar stock a 0 es válido */
    public function testUpdateProductStockToZero(): void
    {
        $productId = $this->createProduct();

        $this->patchJson("/api/products/{$productId}", ['stock' => 0], auth: true);

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/products/{$productId}");
        self::assertSame(0, $this->responseBody()['stock']);
    }

    /** CAT14 — enviar imageUrl: null limpia el campo */
    public function testUpdateProductClearsImageUrl(): void
    {
        $payload             = $this->validProductPayload();
        $payload['imageUrl'] = 'https://example.com/img.jpg';
        $this->postJson('/api/products', $payload, auth: true);
        $productId = $this->responseBody()['productId'];

        $this->patchJson("/api/products/{$productId}", ['imageUrl' => null], auth: true);

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/products/{$productId}");
        self::assertNull($this->responseBody()['imageUrl']);
    }

    /** CAT15 — PATCH sobre producto inexistente → 404 */
    public function testUpdateProductNotFoundReturns404(): void
    {
        $this->patchJson('/api/products/550e8400-e29b-41d4-a716-446655440000', [
            'name' => 'Nuevo nombre',
        ], auth: true);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('product_not_found', $this->responseBody()['error']['code']);
    }

    /** CAT16 — PATCH que genera nombre duplicado → 409 */
    public function testUpdateProductGeneratesDuplicateNameReturns409(): void
    {
        $this->createProduct('Maillot A', 'CYCLING');
        $idB = $this->createProduct('Maillot B', 'CYCLING');

        $this->patchJson("/api/products/{$idB}", ['name' => 'Maillot A'], auth: true);

        self::assertResponseStatusCodeSame(409);
        self::assertSame('duplicate_product_name', $this->responseBody()['error']['code']);
    }

    /** CAT17 — PATCH con precio inválido → 422 */
    public function testUpdateProductInvalidPriceReturns422(): void
    {
        $productId = $this->createProduct();

        $this->patchJson("/api/products/{$productId}", [
            'price' => ['amount' => -100, 'currency' => 'EUR'],
        ], auth: true);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_price', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // DELETE /api/products/{id}
    // ---------------------------------------------------------------

    /** CAT18 — eliminar producto existente */
    public function testDeleteProductSucceeds(): void
    {
        $productId = $this->createProduct();

        $this->client->request('DELETE', "/api/products/{$productId}", server: $this->authHeader());

        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', "/api/products/{$productId}");
        self::assertResponseStatusCodeSame(404);
    }

    /** CAT19 — eliminar producto inexistente → 404 */
    public function testDeleteProductNotFoundReturns404(): void
    {
        $this->client->request('DELETE', '/api/products/550e8400-e29b-41d4-a716-446655440000', server: $this->authHeader());

        self::assertResponseStatusCodeSame(404);
        self::assertSame('product_not_found', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // GET /api/products — List
    // ---------------------------------------------------------------

    /** CAT20 — listado devuelve estructura de paginación correcta */
    public function testListProductsReturnsAll(): void
    {
        $this->createProduct('Mallot Mizuno', 'CYCLING');
        $this->createProduct('Camiseta Nike', 'FITNESS', 'Nike');

        $this->client->request('GET', '/api/products');

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame(2, $body['pagination']['total']);
        self::assertCount(2, $body['data']);

        $item = $body['data'][0];
        foreach (['id', 'name', 'price', 'category', 'brand', 'attributes', 'stock', 'imageUrl', 'createdAt', 'updatedAt'] as $key) {
            self::assertArrayHasKey($key, $item);
        }
    }

    /** CAT21 — paginación respeta page y pageSize */
    public function testListProductsPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createProduct("Producto {$i}", 'CYCLING');
        }

        $this->client->request('GET', '/api/products?page=2&pageSize=2');

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame(5, $body['pagination']['total']);
        self::assertSame(3, $body['pagination']['totalPages']);
        self::assertSame(2, $body['pagination']['page']);
        self::assertSame(2, $body['pagination']['pageSize']);
        self::assertCount(2, $body['data']);
    }

    /** CAT21 — no se solapan ítems entre páginas */
    public function testListProductsPagesDoNotOverlap(): void
    {
        for ($i = 1; $i <= 4; $i++) {
            $this->createProduct("Producto {$i}", 'CYCLING');
        }

        $this->client->request('GET', '/api/products?page=1&pageSize=2');
        $page1Ids = array_column($this->responseBody()['data'], 'id');

        $this->client->request('GET', '/api/products?page=2&pageSize=2');
        $page2Ids = array_column($this->responseBody()['data'], 'id');

        self::assertEmpty(array_intersect($page1Ids, $page2Ids));
    }

    /** CAT22 — filtro por categoría */
    public function testListProductsFiltersByCategory(): void
    {
        $this->createProduct('Mallot Mizuno', 'CYCLING');
        $this->createProduct('Camiseta Nike', 'FITNESS', 'Nike');

        $this->client->request('GET', '/api/products?category=CYCLING');

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame(1, $body['pagination']['total']);
        self::assertSame('CYCLING', $body['data'][0]['category']);
    }

    /** CAT23 — filtro por brand */
    public function testListProductsFiltersByBrand(): void
    {
        $this->createProduct('Mallot Siroko', 'CYCLING', 'Siroko');
        $this->createProduct('Mallot Mizuno', 'CYCLING', 'Mizuno');

        $this->client->request('GET', '/api/products?brand=Siroko');

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame(1, $body['pagination']['total']);
        self::assertSame('Siroko', $body['data'][0]['brand']);
    }

    /** CAT24 — filtro combinado categoría + brand */
    public function testListProductsFiltersByCategoryAndBrand(): void
    {
        $this->createProduct('Mallot Siroko', 'CYCLING', 'Siroko');
        $this->createProduct('Camiseta Siroko', 'FITNESS', 'Siroko');
        $this->createProduct('Mallot Mizuno', 'CYCLING', 'Mizuno');

        $this->client->request('GET', '/api/products?category=CYCLING&brand=Siroko');

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame(1, $body['pagination']['total']);
        self::assertSame('Mallot Siroko', $body['data'][0]['name']);
    }

    /** CAT25 — categoría inválida en filtro → 422 */
    public function testListProductsInvalidCategoryReturns422(): void
    {
        $this->client->request('GET', '/api/products?category=RUNNING');

        self::assertResponseStatusCodeSame(422);
        self::assertSame('invalid_enum_value', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // GET /api/products/{id} — Detail
    // ---------------------------------------------------------------

    /** CAT26 — detalle devuelve datos completos */
    public function testGetProductReturnsCorrectData(): void
    {
        $productId = $this->createProduct();

        $this->client->request('GET', "/api/products/{$productId}");

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();
        self::assertSame($productId, $body['id']);
        self::assertSame('Mallot Mizuno', $body['name']);
        self::assertSame(4999, $body['price']['amount']);
        self::assertSame('EUR', $body['price']['currency']);
        self::assertSame('CYCLING', $body['category']);
        self::assertSame('Mizuno', $body['brand']);
        self::assertSame(10, $body['stock']);
    }

    /** CAT26 — ID inexistente → 404 */
    public function testGetProductNotFoundReturns404(): void
    {
        $this->client->request('GET', '/api/products/550e8400-e29b-41d4-a716-446655440000');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('product_not_found', $this->responseBody()['error']['code']);
    }

    /** CAT26 — UUID malformado → 400 */
    public function testGetProductInvalidUuidReturns400(): void
    {
        $this->client->request('GET', '/api/products/not-a-uuid');

        self::assertResponseStatusCodeSame(400);
        self::assertSame('invalid_id', $this->responseBody()['error']['code']);
    }

    // ---------------------------------------------------------------
    // Seguridad — endpoints de admin
    // ---------------------------------------------------------------

    /** CAT27 — POST sin token → 401 */
    public function testCreateProductRequiresAuth(): void
    {
        $this->postJson('/api/products', $this->validProductPayload());

        self::assertResponseStatusCodeSame(401);
    }

    /** CAT27 — PATCH sin token → 401 */
    public function testUpdateProductRequiresAuth(): void
    {
        $productId = $this->createProduct();

        $this->patchJson("/api/products/{$productId}", ['name' => 'Nuevo nombre']);

        self::assertResponseStatusCodeSame(401);
    }

    /** CAT27 — DELETE sin token → 401 */
    public function testDeleteProductRequiresAuth(): void
    {
        $productId = $this->createProduct();

        $this->client->request('DELETE', "/api/products/{$productId}");

        self::assertResponseStatusCodeSame(401);
    }

    /** CAT28 — token incorrecto → 401 */
    public function testCreateProductWrongTokenReturns401(): void
    {
        $this->client->request('POST', '/api/products', server: [
            'CONTENT_TYPE'       => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer token-incorrecto',
        ], content: json_encode($this->validProductPayload()));

        self::assertResponseStatusCodeSame(401);
    }

    /** CAT28 — token incorrecto en PATCH → 401 */
    public function testUpdateProductWrongTokenReturns401(): void
    {
        $productId = $this->createProduct();

        $this->client->request('PATCH', "/api/products/{$productId}", server: [
            'CONTENT_TYPE'       => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer wrong-token',
        ], content: json_encode(['stock' => 5]));

        self::assertResponseStatusCodeSame(401);
    }

    /** CAT29 — GET /api/products es público */
    public function testListProductsIsPublicWithoutAuth(): void
    {
        $this->createProduct();

        $this->client->request('GET', '/api/products');

        self::assertResponseStatusCodeSame(200);
    }

    /** CAT29 — GET /api/products/{id} es público */
    public function testGetProductIsPublicWithoutAuth(): void
    {
        $productId = $this->createProduct();

        $this->client->request('GET', "/api/products/{$productId}");

        self::assertResponseStatusCodeSame(200);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function createProduct(string $name = 'Mallot Mizuno', string $category = 'CYCLING', string $brand = 'Mizuno'): string
    {
        $payload             = $this->validProductPayload();
        $payload['name']     = $name;
        $payload['category'] = $category;
        $payload['brand']    = $brand;

        $this->postJson('/api/products', $payload, auth: true);

        return $this->responseBody()['productId'];
    }

    private function validProductPayload(): array
    {
        return [
            'name'        => 'Mallot Mizuno',
            'description' => 'Mallot de ciclismo profesional',
            'price'       => ['amount' => 4999, 'currency' => 'EUR'],
            'category'    => 'CYCLING',
            'brand'       => 'Mizuno',
            'attributes'  => ['color' => ['negro', 'azul']],
            'stock'       => 10,
        ];
    }

    private function postJson(string $uri, array $body, bool $auth = false): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($auth) {
            $server = array_merge($server, $this->authHeader());
        }

        $this->client->request('POST', $uri, server: $server, content: json_encode($body));
    }

    private function patchJson(string $uri, array $body, bool $auth = false): void
    {
        $server = ['CONTENT_TYPE' => 'application/json'];
        if ($auth) {
            $server = array_merge($server, $this->authHeader());
        }

        $this->client->request('PATCH', $uri, server: $server, content: json_encode($body));
    }

    private function authHeader(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminKey];
    }

    private function responseBody(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
