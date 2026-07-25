<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725174000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chronicle VK crosspost tracking fields';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry ADD vk_post_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE chronicle_entry ADD vk_posted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE chronicle_entry ADD vk_crosspost_error LONGTEXT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CHRONICLE_ENTRY_VK_POST ON chronicle_entry (vk_post_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_CHRONICLE_ENTRY_VK_POST ON chronicle_entry');
        $this->addSql('ALTER TABLE chronicle_entry DROP vk_post_id');
        $this->addSql('ALTER TABLE chronicle_entry DROP vk_posted_at');
        $this->addSql('ALTER TABLE chronicle_entry DROP vk_crosspost_error');
    }
}
