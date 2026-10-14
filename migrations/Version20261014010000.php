<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'InquiryStatus: map in_progress→new, archived→done (только New/Done)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE inquiry SET status = 'new' WHERE status = 'in_progress'");
        $this->addSql("UPDATE inquiry SET status = 'done' WHERE status = 'archived'");
    }

    public function down(Schema $schema): void
    {
        // Обратный маппинг неоднозначен — статусы уже схлопнуты.
    }
}
