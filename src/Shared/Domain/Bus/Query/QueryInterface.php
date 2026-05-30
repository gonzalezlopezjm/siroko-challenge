<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Query;

/**
 * Interfaz marcadora para todas las Queries del sistema.
 * Cualquier clase que la implemente será enrutada al transport síncrono.
 */
interface QueryInterface {}
