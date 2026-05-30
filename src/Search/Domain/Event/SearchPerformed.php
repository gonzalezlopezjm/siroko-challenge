<?php

declare(strict_types=1);

namespace App\Search\Domain\Event;

use App\Shared\Domain\Event\DomainEventInterface;

final class SearchPerformed implements DomainEventInterface
{
    private readonly \DateTimeImmutable $occurredOn;

    public function __construct(
        private readonly string $query,
        private readonly int $resultsCount,
        private readonly bool $keywordFallback,
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function aggregateId(): string              { return md5($this->query); }
    public function occurredOn(): \DateTimeImmutable   { return $this->occurredOn; }
    public function query(): string                    { return $this->query; }
    public function resultsCount(): int                { return $this->resultsCount; }
    public function keywordFallback(): bool            { return $this->keywordFallback; }
}
