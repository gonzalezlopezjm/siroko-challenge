<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Catalog\Application\Command\CreateProduct\CreateProductCommand;
use App\Catalog\Domain\Exception\DuplicateProductNameException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsCommand(
    name: 'app:fixtures:load',
    description: 'Carga los productos de muestra desde docs/fixtures.json',
)]
final class LoadFixturesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        #[Autowire('%kernel.project_dir%')] private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('skip-existing', null, InputOption::VALUE_NONE, 'Omite silenciosamente los productos que ya existen');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $path = $this->projectDir . '/docs/fixtures.json';

        if (!file_exists($path)) {
            $io->error(sprintf('No se encuentra el archivo de fixtures: %s', $path));

            return Command::FAILURE;
        }

        $data     = json_decode(file_get_contents($path), true);
        $products = $data['products'] ?? [];

        if (empty($products)) {
            $io->warning('El archivo fixtures.json no contiene productos.');

            return Command::SUCCESS;
        }

        $io->title(sprintf('Cargando %d productos de muestra Siroko', count($products)));

        $io->progressStart(count($products));

        $loaded  = 0;
        $skipped = 0;
        $errors  = [];

        foreach ($products as $product) {
            try {
                $this->commandBus->dispatch(new CreateProductCommand(
                    name: (string) $product['name'],
                    description: (string) ($product['description'] ?? ''),
                    priceAmount: (int) $product['price']['amount'],
                    priceCurrency: (string) $product['price']['currency'],
                    category: (string) $product['category'],
                    brand: (string) $product['brand'],
                    attributes: (array) ($product['attributes'] ?? []),
                    stock: (int) $product['stock'],
                    imageUrl: $product['imageUrl'] ?? null,
                ));
                ++$loaded;
            } catch (HandlerFailedException $e) {
                $cause = $e->getPrevious() ?? $e;
                if ($cause instanceof DuplicateProductNameException) {
                    ++$skipped;
                } else {
                    $errors[] = sprintf('"%s": %s', $product['name'], $cause->getMessage());
                }
            } catch (\Throwable $e) {
                $errors[] = sprintf('"%s": %s', $product['name'], $e->getMessage());
            }

            $io->progressAdvance();
        }

        $io->progressFinish();

        $io->success(sprintf(
            'Cargados: %d productos.%s',
            $loaded,
            $skipped > 0 ? sprintf(' Omitidos (ya existían): %d.', $skipped) : '',
        ));

        if (!empty($errors)) {
            $io->warning('Errores durante la carga:');
            foreach ($errors as $error) {
                $io->writeln('  · ' . $error);
            }
        }

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
    }
}
