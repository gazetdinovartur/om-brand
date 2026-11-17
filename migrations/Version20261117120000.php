<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Speak meeting: waiting_room, wait_for_host, transcription_autostart; practice_session kind default speak';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE speak_meeting ADD waiting_room TINYINT(1) DEFAULT 0 NOT NULL, ADD wait_for_host TINYINT(1) DEFAULT 1 NOT NULL, ADD transcription_autostart TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql("ALTER TABLE practice_session ALTER kind SET DEFAULT 'speak'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE speak_meeting DROP waiting_room, DROP wait_for_host, DROP transcription_autostart');
        $this->addSql("ALTER TABLE practice_session ALTER kind SET DEFAULT 'live'");
    }
}
