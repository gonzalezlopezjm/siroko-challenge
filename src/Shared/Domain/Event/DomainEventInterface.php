<?php

declare(strict_types=1);

namespace App\Shared\Domain\Event;

/**
 * Interfaz marcadora para todos los Domain Events del sistema.
 * Cualquier clase que la implemente será enrutada al transport asíncrono,
 * desacoplando la indexación y otros efectos secundarios del request HTTP.
 */
interface DomainEventInterface
{
    public function occurredOn(): \DateTimeImmutable;
    public function aggregateId(): string;
}
