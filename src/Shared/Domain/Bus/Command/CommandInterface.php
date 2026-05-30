<?php

declare(strict_types=1);

namespace App\Shared\Domain\Bus\Command;

/**
 * Interfaz marcadora para todos los Commands del sistema.
 * Cualquier clase que la implemente será enrutada al transport síncrono
 * de Symfony Messenger sin necesidad de listarlo en messenger.yaml.
 */
interface CommandInterface {}
