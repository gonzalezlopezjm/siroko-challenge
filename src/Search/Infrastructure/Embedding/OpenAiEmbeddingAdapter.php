<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Embedding;

use App\Search\Domain\Port\EmbeddingPort;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class OpenAiEmbeddingAdapter implements EmbeddingPort
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $model,
    ) {}

    public function embed(string $text): array
    {
        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/embeddings', [
            'json' => ['model' => $this->model, 'input' => $text],
        ]);

        return $response->toArray()['data'][0]['embedding'];
    }
}
