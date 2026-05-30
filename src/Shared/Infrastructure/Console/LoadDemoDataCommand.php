<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Console;

use App\Cart\Application\Command\AddItemToCart\AddItemToCartCommand;
use App\Cart\Application\Command\CreateCart\CreateCartCommand;
use App\Catalog\Domain\Repository\ProductRepositoryInterface;
use App\Order\Application\Command\CancelOrder\CancelOrderCommand;
use App\Order\Application\Command\CheckoutOrder\CheckoutOrderCommand;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

#[AsCommand(
    name: 'app:demo:setup',
    description: 'Crea pedidos y emails de demo para revisión inicial del proyecto',
)]
final class LoadDemoDataCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Configurando datos de demo');

        $existing = (int) $this->connection->fetchOne(
            "SELECT COUNT(*) FROM orders WHERE customer_id LIKE 'demo-%'"
        );

        if ($existing >= 3) {
            $io->success('Los datos de demo ya existen, omitiendo.');
            return Command::SUCCESS;
        }

        $products = $this->productRepository->findByCriteria(null, null, 1, 5);

        if (count($products) < 2) {
            $io->warning('No hay suficientes productos. Ejecuta app:fixtures:load primero.');
            return Command::FAILURE;
        }

        $demos = [
            [
                'customerId' => 'demo-user-1',
                'email'      => 'carlos.garcia@ejemplo.com',
                'name'       => 'Carlos',
                'street'     => 'Calle Goya 42',
                'city'       => 'Madrid',
                'postal'     => '28001',
                'country'    => 'ES',
                'qty'        => 2,
                'cancel'     => false,
            ],
            [
                'customerId' => 'demo-user-2',
                'email'      => 'ana.martinez@ejemplo.com',
                'name'       => 'Ana',
                'street'     => 'Passeig de Gràcia 88',
                'city'       => 'Barcelona',
                'postal'     => '08008',
                'country'    => 'ES',
                'qty'        => 1,
                'cancel'     => true,
            ],
            [
                'customerId' => 'demo-user-3',
                'email'      => 'javier.lopez@ejemplo.com',
                'name'       => 'Javier',
                'street'     => 'Avenida de la Constitución 5',
                'city'       => 'Sevilla',
                'postal'     => '41001',
                'country'    => 'ES',
                'qty'        => 3,
                'cancel'     => false,
            ],
        ];

        foreach ($demos as $i => $demo) {
            $product = $products[$i % count($products)];

            $cartId = $this->dispatch(new CreateCartCommand($demo['customerId']));
            $this->commandBus->dispatch(new AddItemToCartCommand($cartId, $product->id()->value(), $demo['qty']));

            $orderId = $this->dispatch(new CheckoutOrderCommand(
                cartId: $cartId,
                customerId: $demo['customerId'],
                customerEmail: $demo['email'],
                shippingStreet: $demo['street'],
                shippingCity: $demo['city'],
                shippingPostalCode: $demo['postal'],
                shippingCountry: $demo['country'],
            ));

            $status = 'creado';
            if ($demo['cancel']) {
                $this->commandBus->dispatch(new CancelOrderCommand($orderId));
                $status = 'creado y cancelado';
            }

            $io->writeln(sprintf(
                '  ✓ Pedido de %s (%s) — %s [%s]',
                $demo['name'],
                $demo['email'],
                substr($orderId, 0, 8),
                $status,
            ));
        }

        $io->success('Demo data lista. Los emails se enviarán cuando arranque el worker.');

        return Command::SUCCESS;
    }

    private function dispatch(object $command): string
    {
        return $this->commandBus
            ->dispatch($command)
            ->last(HandledStamp::class)
            ->getResult();
    }
}
