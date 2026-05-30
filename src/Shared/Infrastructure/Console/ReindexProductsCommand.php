<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Search\Application\Service\ProductIndexer;
use App\Search\Domain\Port\SemanticSearchPort;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search:reindex',
    description: 'Re-indexa todos los productos del catálogo en Qdrant',
)]
final class ReindexProductsCommand extends Command
{
    private const BATCH_SIZE = 20;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductIndexer $indexer,
        private readonly SemanticSearchPort $semanticSearchPort,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'batch-size',
            null,
            InputOption::VALUE_REQUIRED,
            'Número de productos por lote',
            self::BATCH_SIZE,
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io        = new SymfonyStyle($input, $output);
        $batchSize = (int) $input->getOption('batch-size');

        $io->title('Re-indexando productos en Qdrant');

        $total = $this->productRepository->countByCriteria(null, null);
        $pages = (int) ceil($total / $batchSize);

        if ($total === 0) {
            $io->warning('No hay productos en el catálogo. Carga los fixtures primero con app:fixtures:load.');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf('Productos encontrados: <info>%d</info>. Lotes: <info>%d</info>.', $total, $pages));
        $io->writeln('Limpiando colección Qdrant...');
        $this->semanticSearchPort->clear();

        $io->progressStart($total);

        $indexed = 0;
        $errors  = [];

        for ($page = 1; $page <= $pages; $page++) {
            foreach ($this->productRepository->findByCriteria(null, null, $page, $batchSize) as $product) {
                try {
                    $this->indexer->index(
                        productId:     $product->id()->value(),
                        name:          $product->name()->value(),
                        description:   $product->description()->value(),
                        category:      $product->category()->value,
                        brand:         $product->brand()->value(),
                        priceAmount:   $product->price()->amount(),
                        priceCurrency: $product->price()->currency()->value,
                        stock:         $product->stock()->quantity(),
                        attributes:    $product->attributes()->toArray(),
                    );
                    ++$indexed;
                } catch (\Throwable $e) {
                    $errors[] = sprintf('"%s": %s', $product->name()->value(), $e->getMessage());
                }

                $io->progressAdvance();
            }
        }

        $io->progressFinish();
        $io->success(sprintf('Indexados: %d / %d productos en Qdrant.', $indexed, $total));

        if (!empty($errors)) {
            $io->warning('Errores durante la indexación:');
            foreach ($errors as $error) {
                $io->writeln('  · ' . $error);
            }

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
