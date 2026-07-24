<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260724220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chronicle entries: sort_order for admin DnD and public feed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry ADD sort_order INT NOT NULL DEFAULT 0');
        // Preserve current chronological feed: newest first → lower sort_order
        $this->addSql(<<<'SQL'
            UPDATE chronicle_entry e
            INNER JOIN (
                SELECT id, ROW_NUMBER() OVER (
                    ORDER BY published_at DESC, id DESC
                ) - 1 AS rn
                FROM chronicle_entry
            ) ranked ON ranked.id = e.id
            SET e.sort_order = ranked.rn
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry DROP sort_order');
    }
}
