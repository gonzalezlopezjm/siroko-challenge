<?php

declare(strict_types=1);

namespace Tests\Stub\Search;

use App\Search\Domain\Port\EmbeddingPort;

final class InMemoryEmbeddingAdapter implements EmbeddingPort
{
    public function embed(string $text): array
    {
        $vector    = array_fill(0, 1536, 0.0);
        $vector[0] = 1.0;

        return $vector;
    }
}
