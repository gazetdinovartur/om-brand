<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260905120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chronicle Telegram channel crosspost fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry ADD tg_message_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE chronicle_entry ADD tg_posted_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE chronicle_entry ADD tg_crosspost_error LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE chronicle_entry ADD tg_crosspost_requested TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry DROP tg_message_id');
        $this->addSql('ALTER TABLE chronicle_entry DROP tg_posted_at');
        $this->addSql('ALTER TABLE chronicle_entry DROP tg_crosspost_error');
        $this->addSql('ALTER TABLE chronicle_entry DROP tg_crosspost_requested');
    }
}
