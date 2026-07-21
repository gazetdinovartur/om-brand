<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260721161500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chronicle admin-only entries (mirror channel): hidden from web unless ROLE_ADMIN';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry ADD is_admin_only TINYINT(1) NOT NULL DEFAULT 0');
        $this->addSql("UPDATE chronicle_entry SET is_admin_only = 1 WHERE source_key LIKE 'tg:mirror:%'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry DROP is_admin_only');
    }
}
