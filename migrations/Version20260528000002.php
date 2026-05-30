<?php

declare(strict_types=1);

namespace App\Infrastructure\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create carts and orders tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS carts (
                id              VARCHAR(36)  NOT NULL,
                customer_id     VARCHAR(255) DEFAULT NULL,
                items           TEXT         NOT NULL DEFAULT '[]',
                created_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS orders (
                id                   VARCHAR(36)  NOT NULL,
                customer_id          VARCHAR(255) DEFAULT NULL,
                total_amount         INT          NOT NULL,
                total_currency       VARCHAR(3)   NOT NULL,
                status               VARCHAR(20)  NOT NULL,
                shipping_street      VARCHAR(255) NOT NULL,
                shipping_city        VARCHAR(100) NOT NULL,
                shipping_postal_code VARCHAR(20)  NOT NULL,
                shipping_country     VARCHAR(2)   NOT NULL,
                lines                TEXT         NOT NULL DEFAULT '[]',
                created_at           TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY (id)
            )
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS orders');
        $this->addSql('DROP TABLE IF EXISTS carts');
    }
}
