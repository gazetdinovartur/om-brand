<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260722010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Content likes: denormalized like_count + anonymous visitor likes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry ADD like_count INT NOT NULL DEFAULT 0');
        $this->addSql('ALTER TABLE case_study ADD like_count INT NOT NULL DEFAULT 0');
        $this->addSql('CREATE TABLE content_like (id INT AUTO_INCREMENT NOT NULL, target_type VARCHAR(16) NOT NULL, target_id INT NOT NULL, visitor_token VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_content_like_visitor (target_type, target_id, visitor_token), INDEX idx_content_like_target (target_type, target_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE content_like');
        $this->addSql('ALTER TABLE case_study DROP like_count');
        $this->addSql('ALTER TABLE chronicle_entry DROP like_count');
    }
}
