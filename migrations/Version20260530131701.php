<?php

declare(strict_types=1);

namespace App\Infrastructure\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530131701 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create app_metrics table for observability';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("
            CREATE TABLE app_metrics (
                id          BIGSERIAL PRIMARY KEY,
                occurred_at TIMESTAMPTZ          NOT NULL DEFAULT NOW(),
                metric      VARCHAR(100)          NOT NULL,
                value       DOUBLE PRECISION      NOT NULL DEFAULT 1,
                tags        JSONB
            )
        ");
        $this->addSql('CREATE INDEX idx_app_metrics_time   ON app_metrics (occurred_at DESC)');
        $this->addSql('CREATE INDEX idx_app_metrics_metric ON app_metrics (metric, occurred_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS app_metrics');
    }
}
