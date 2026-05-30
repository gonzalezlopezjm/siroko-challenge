<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:db:reset',
    description: 'Vacía la base de datos, ejecuta las migraciones y carga los fixtures de muestra',
)]
final class ResetDatabaseCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption(
            'force',
            'f',
            InputOption::VALUE_NONE,
            'Ejecuta sin pedir confirmación (útil en CI/scripts)',
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$input->getOption('force')) {
            $io->caution('Esta operación eliminará TODOS los datos de la base de datos.');

            if (!$io->confirm('¿Continuar?', false)) {
                $io->comment('Operación cancelada.');

                return Command::SUCCESS;
            }
        }

        $io->section('1/3 — Eliminando esquema actual');
        $exitCode = $this->call('doctrine:schema:drop', ['--full-database' => true, '--force' => true], $output);
        if ($exitCode !== Command::SUCCESS) {
            $io->error('Falló doctrine:schema:drop.');

            return Command::FAILURE;
        }

        $io->section('2/3 — Ejecutando migraciones');
        $exitCode = $this->call('doctrine:migrations:migrate', ['--no-interaction' => true], $output);
        if ($exitCode !== Command::SUCCESS) {
            $io->error('Falló doctrine:migrations:migrate.');

            return Command::FAILURE;
        }

        $io->section('3/4 — Cargando fixtures de muestra');
        $exitCode = $this->call('app:fixtures:load', [], $output);
        if ($exitCode !== Command::SUCCESS) {
            $io->error('Falló app:fixtures:load.');

            return Command::FAILURE;
        }

        $io->section('4/4 — Re-indexando productos en Qdrant');
        $exitCode = $this->call('app:search:reindex', [], $output);
        if ($exitCode !== Command::SUCCESS) {
            $io->warning('La indexación en Qdrant falló (comprueba OPENAI_API_KEY). La API sigue operativa sin búsqueda semántica.');
        }

        $io->success('Base de datos reseteada, fixtures cargados y productos indexados en Qdrant.');

        return Command::SUCCESS;
    }

    private function call(string $name, array $args, OutputInterface $output): int
    {
        $input = new ArrayInput(array_merge(['command' => $name], $args));
        $input->setInteractive(false);

        return $this->getApplication()->find($name)->run($input, $output);
    }
}
