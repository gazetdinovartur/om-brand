<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chronicle VK crosspost requested flag (checkbox-driven)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry ADD vk_crosspost_requested TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry DROP vk_crosspost_requested');
    }
}
