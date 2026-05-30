<?php

declare(strict_types=1);

namespace App\Search\Domain\Port;

interface EmbeddingPort
{
    /** @return float[] */
    public function embed(string $text): array;
}
