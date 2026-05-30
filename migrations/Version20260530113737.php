<?php

declare(strict_types=1);

namespace App\Infrastructure\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530113737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add customer_email to orders table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders ADD customer_email VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE orders DROP COLUMN customer_email');
    }
}
