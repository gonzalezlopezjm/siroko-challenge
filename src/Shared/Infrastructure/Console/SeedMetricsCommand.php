<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Shared\Infrastructure\Metrics\MetricWriter;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:metrics:seed',
    description: 'Genera datos de métricas de ejemplo para el dashboard de Grafana',
)]
final class SeedMetricsCommand extends Command
{
    public function __construct(
        private readonly MetricWriter $writer,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Días de historia a generar', 7);
        $this->addOption('skip-if-exists', null, InputOption::VALUE_NONE, 'No ejecutar si ya hay datos en app_metrics');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        if ($input->getOption('skip-if-exists')) {
            $count = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM app_metrics');
            if ($count > 0) {
                $io->writeln(sprintf('<info>app_metrics ya tiene %d filas, omitiendo.</info>', $count));
                return Command::SUCCESS;
            }
        }

        $io->title("Generando métricas de ejemplo ({$days} días)");

        $now       = new \DateTimeImmutable();
        $total     = 0;

        for ($daysAgo = $days - 1; $daysAgo >= 0; $daysAgo--) {
            $dayStart = $now->modify("-{$daysAgo} days")->setTime(0, 0, 0);

            for ($hour = 0; $hour < 24; $hour++) {
                $hourTs = $dayStart->setTime($hour, 0, 0);

                // Tráfico con patrón realista: picos a media mañana y tarde
                $isBusiness = $hour >= 9 && $hour <= 21;
                $isPeak     = ($hour >= 10 && $hour <= 13) || ($hour >= 17 && $hour <= 20);

                $orders    = $isPeak ? rand(8, 18) : ($isBusiness ? rand(3, 8) : rand(0, 2));
                $searches  = $isPeak ? rand(30, 80) : ($isBusiness ? rand(10, 30) : rand(1, 8));

                // Pedidos creados
                for ($i = 0; $i < $orders; $i++) {
                    $at = $this->randomTimestampInHour($hourTs);
                    $this->writer->record('orders.created', 1, [], $at);
                    $total++;

                    // ~12% de cancelaciones en las horas siguientes
                    if (rand(1, 100) <= 12) {
                        $cancelDelay = rand(300, 7200);
                        $cancelAt    = \DateTimeImmutable::createFromFormat(
                            'U',
                            (string) ($at->getTimestamp() + $cancelDelay)
                        );
                        if ($cancelAt !== false && $cancelAt <= $now) {
                            $this->writer->record('orders.cancelled', 1, [], $cancelAt);
                            $total++;
                        }
                    }

                    // Revenue (~40-120 €/pedido)
                    $revenue = rand(40, 120);
                    $this->writer->record('orders.revenue', (float) $revenue, [], $at);
                    $total++;
                }

                // Búsquedas
                for ($i = 0; $i < $searches; $i++) {
                    $at      = $this->randomTimestampInHour($hourTs);
                    $noResults = rand(1, 100) <= 8;  // 8% sin resultados
                    $keyword   = rand(1, 100) <= 5;  // 5% fallback keyword
                    $this->writer->record('search.performed', 1, [
                        'results'          => $noResults ? 0 : rand(1, 10),
                        'keyword_fallback' => $keyword,
                        'empty'            => $noResults,
                    ], $at);
                    $total++;

                    if ($noResults) {
                        $this->writer->record('search.no_results', 1, [], $at);
                        $total++;
                    }
                }
            }

            $io->writeln(sprintf('  ✓ Día -%d (%s)', $daysAgo, $dayStart->format('Y-m-d')));
        }

        $io->success(sprintf('Insertados %d registros de métricas en app_metrics.', $total));

        return Command::SUCCESS;
    }

    private function randomTimestampInHour(\DateTimeImmutable $hourStart): \DateTimeImmutable
    {
        $offset = rand(0, 3599);
        $ts     = \DateTimeImmutable::createFromFormat('U', (string) ($hourStart->getTimestamp() + $offset));

        return $ts !== false ? $ts : $hourStart;
    }
}
