<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

use Doctrine\DBAL\Connection;

final class MetricWriter
{
    public function __construct(private readonly Connection $connection) {}

    /** @param array<string, mixed> $tags */
    public function record(string $metric, float $value = 1.0, array $tags = [], ?\DateTimeImmutable $at = null): void
    {
        $this->connection->insert('app_metrics', [
            'occurred_at' => ($at ?? new \DateTimeImmutable())->format('Y-m-d H:i:s.uP'),
            'metric'      => $metric,
            'value'       => $value,
            'tags'        => empty($tags) ? null : json_encode($tags),
        ]);
    }
}
