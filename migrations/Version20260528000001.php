<?php

declare(strict_types=1);

namespace App\Infrastructure\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260528000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create products table (Catalog BC)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE IF NOT EXISTS products (
            id             VARCHAR(36)   NOT NULL,
            name           VARCHAR(255)  NOT NULL,
            description    TEXT          NOT NULL DEFAULT \'\',
            price_amount   INT           NOT NULL,
            price_currency VARCHAR(3)    NOT NULL,
            category       VARCHAR(50)   NOT NULL,
            brand          VARCHAR(100)  NOT NULL,
            attributes     JSONB         NOT NULL DEFAULT \'[]\',
            stock          INT           NOT NULL DEFAULT 0,
            image_url      VARCHAR(2048)     NULL,
            created_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE UNIQUE INDEX IF NOT EXISTS uq_product_name_category ON products (name, category)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_products_category ON products (category)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_products_brand    ON products (brand)');
        $this->addSql('COMMENT ON COLUMN products.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN products.updated_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS products');
    }
}
