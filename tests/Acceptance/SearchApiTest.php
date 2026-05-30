<?php

declare(strict_types=1);

namespace App\Tests\Acceptance;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Tests\Stub\Search\InMemorySemanticSearchAdapter;

final class SearchApiTest extends WebTestCase
{
    private KernelBrowser $client;

    private string $adminKey;

    protected function setUp(): void
    {
        $this->client = self::createClient(['environment' => 'test', 'debug' => true]);
        // Disable kernel reboot between requests so that in-memory stores
        // (InMemorySemanticSearchAdapter, InMemoryTransport) persist across calls.
        $this->client->disableReboot();

        $this->adminKey = self::getContainer()->getParameter('admin_api_key');

        $em   = self::getContainer()->get(EntityManagerInterface::class);
        $tool = new SchemaTool($em);
        $tool->dropDatabase();
        $tool->createSchema($em->getMetadataFactory()->getAllMetadata());

        // Clear the in-memory semantic search store between tests
        $adapter = self::getContainer()->get(InMemorySemanticSearchAdapter::class);
        $adapter->store = [];
    }

    public function testSearchReturnsIndexedProduct(): void
    {
        // Create a product via the API — this fires ProductCreated event which
        // goes into the async in-memory transport
        $this->postJson('/api/products', $this->validProductPayload(), auth: true);
        self::assertResponseStatusCodeSame(201);

        // Consume the queued async events so that IndexProductHandler runs,
        // which calls InMemorySemanticSearchAdapter::upsert
        $this->consumeAsyncMessages();

        // Now search — InMemoryEmbeddingAdapter returns a constant vector,
        // InMemorySemanticSearchAdapter::search returns all stored products
        $this->client->request('GET', '/api/search?q=mallot+mizuno');

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();

        self::assertArrayHasKey('results', $body);
        self::assertArrayHasKey('meta', $body);
        self::assertCount(1, $body['results']);

        $result = $body['results'][0];
        self::assertArrayHasKey('id', $result);
        self::assertSame('Mallot Mizuno', $result['name']);
        self::assertSame('Mallot de ciclismo profesional', $result['description']);
        self::assertSame(['amount' => 4999, 'currency' => 'EUR'], $result['price']);
        self::assertSame('CYCLING', $result['category']);
        self::assertSame('Mizuno', $result['brand']);
        self::assertSame(10, $result['stock']);
        self::assertArrayHasKey('attributes', $result);
        self::assertArrayHasKey('createdAt', $result);
        self::assertArrayHasKey('updatedAt', $result);
        self::assertSame(1, $body['meta']['results_count']);
    }

    /**
     * Manually consume all pending messages from the async in-memory transport.
     * Dispatches each envelope with a ReceivedStamp so the routing middleware
     * treats it as received (not re-routed to async) and passes it to handlers.
     */
    private function consumeAsyncMessages(): void
    {
        $container = self::getContainer();

        /** @var InMemoryTransport $transport */
        $transport = $container->get('messenger.transport.async');
        /** @var MessageBusInterface $bus */
        $bus = $container->get(MessageBusInterface::class);

        foreach ($transport->get() as $envelope) {
            // Adding ReceivedStamp prevents the SendMessageMiddleware from
            // re-routing the message to the async transport again.
            $envelopeWithStamp = $envelope->with(new ReceivedStamp('async'));
            $bus->dispatch($envelopeWithStamp);
            $transport->ack($envelope);
        }
    }

    public function testSearchEmptyQueryReturns422(): void
    {
        $this->client->request('GET', '/api/search?q=');

        self::assertResponseStatusCodeSame(422);
        $body = $this->responseBody();
        self::assertSame('invalid_search_query', $body['error']['code']);
    }

    public function testSearchWithNoResultsReturnsEmptyList(): void
    {
        // No products indexed, so search should return empty
        $this->client->request('GET', '/api/search?q=zapatillas+running+nike');

        self::assertResponseIsSuccessful();
        $body = $this->responseBody();

        self::assertArrayHasKey('results', $body);
        self::assertArrayHasKey('meta', $body);
        self::assertEmpty($body['results']);
        self::assertSame(0, $body['meta']['results_count']);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

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

    private function authHeader(): array
    {
        return ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->adminKey];
    }

    private function responseBody(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true);
    }
}
