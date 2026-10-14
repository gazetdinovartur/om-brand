<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Практика: баланс встреч на PaymentOffer + таблица practice_session';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_offer ADD practice_format VARCHAR(16) DEFAULT NULL, ADD sessions_total INT NOT NULL DEFAULT 0, ADD sessions_left INT NOT NULL DEFAULT 0');

        $this->addSql("UPDATE payment_offer SET practice_format = 'first', sessions_total = 1, sessions_left = CASE WHEN status = 'paid' THEN 1 ELSE 1 END WHERE note LIKE '%· first%'");
        $this->addSql("UPDATE payment_offer SET practice_format = 'single', sessions_total = 1, sessions_left = CASE WHEN status = 'paid' THEN 1 ELSE 1 END WHERE note LIKE '%· single%'");
        $this->addSql("UPDATE payment_offer SET practice_format = 'cycle', sessions_total = 4, sessions_left = CASE WHEN status = 'paid' THEN 4 ELSE 4 END WHERE note LIKE '%· cycle%'");

        $this->addSql('CREATE TABLE practice_session (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, starts_at DATETIME NOT NULL, status VARCHAR(255) NOT NULL, kinescope_event_id VARCHAR(128) DEFAULT NULL, kinescope_video_id VARCHAR(128) DEFAULT NULL, kinescope_chat_embed_url VARCHAR(500) DEFAULT NULL, notes LONGTEXT DEFAULT NULL, debited_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, INDEX idx_practice_session_status (status), INDEX idx_practice_session_event (kinescope_event_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE practice_session');
        $this->addSql('ALTER TABLE payment_offer DROP practice_format, DROP sessions_total, DROP sessions_left');
    }
}
