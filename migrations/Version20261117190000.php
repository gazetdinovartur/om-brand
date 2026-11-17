<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Practice session attendance: debit only offers that entered the room';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE practice_session_attendance (id INT AUTO_INCREMENT NOT NULL, session_id INT NOT NULL, payment_offer_id INT NOT NULL, created_at DATETIME NOT NULL, INDEX idx_practice_attendance_session (session_id), UNIQUE INDEX uniq_practice_attendance_session_offer (session_id, payment_offer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE practice_session_attendance ADD CONSTRAINT FK_PRACTICE_ATT_SESSION FOREIGN KEY (session_id) REFERENCES practice_session (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE practice_session_attendance ADD CONSTRAINT FK_PRACTICE_ATT_OFFER FOREIGN KEY (payment_offer_id) REFERENCES payment_offer (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE practice_session_attendance DROP FOREIGN KEY FK_PRACTICE_ATT_SESSION');
        $this->addSql('ALTER TABLE practice_session_attendance DROP FOREIGN KEY FK_PRACTICE_ATT_OFFER');
        $this->addSql('DROP TABLE practice_session_attendance');
    }
}
