<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Kinescope API: kind/RTMP/Speak fields on practice_session + speak_meeting + gallery meta';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE practice_session ADD kind VARCHAR(16) NOT NULL DEFAULT 'live', ADD kinescope_room_id VARCHAR(128) DEFAULT NULL, ADD kinescope_room_code VARCHAR(64) DEFAULT NULL, ADD kinescope_ingest_url VARCHAR(500) DEFAULT NULL, ADD kinescope_stream_key VARCHAR(255) DEFAULT NULL, ADD poster_url VARCHAR(500) DEFAULT NULL, ADD duration_seconds INT DEFAULT NULL, ADD recording_ready_at DATETIME DEFAULT NULL");
        $this->addSql('CREATE INDEX idx_practice_session_room ON practice_session (kinescope_room_id)');

        $this->addSql('CREATE TABLE speak_meeting (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, starts_at DATETIME DEFAULT NULL, kinescope_room_id VARCHAR(128) DEFAULT NULL, kinescope_room_code VARCHAR(64) DEFAULT NULL, speak_embed_url VARCHAR(500) DEFAULT NULL, autorecording TINYINT(1) NOT NULL, room_type VARCHAR(32) NOT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX uniq_speak_meeting_token (token), INDEX idx_speak_meeting_room (kinescope_room_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE speak_meeting');
        $this->addSql('DROP INDEX idx_practice_session_room ON practice_session');
        $this->addSql('ALTER TABLE practice_session DROP kind, DROP kinescope_room_id, DROP kinescope_room_code, DROP kinescope_ingest_url, DROP kinescope_stream_key, DROP poster_url, DROP duration_seconds, DROP recording_ready_at');
    }
}
