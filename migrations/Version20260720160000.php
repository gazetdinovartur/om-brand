<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chronicle: eras, tags, series, entries, blocks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE chronicle_era (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(120) NOT NULL, slug VARCHAR(80) NOT NULL, period_label VARCHAR(80) DEFAULT NULL, color VARCHAR(7) DEFAULT NULL, description LONGTEXT DEFAULT NULL, sort_order INT NOT NULL, UNIQUE INDEX UNIQ_CHRONICLE_ERA_SLUG (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE chronicle_series (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, slug VARCHAR(80) NOT NULL, description LONGTEXT DEFAULT NULL, sort_order INT NOT NULL, UNIQUE INDEX UNIQ_CHRONICLE_SERIES_SLUG (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE chronicle_tag (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, slug VARCHAR(80) NOT NULL, UNIQUE INDEX UNIQ_CHRONICLE_TAG_SLUG (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE chronicle_entry (id INT AUTO_INCREMENT NOT NULL, era_id INT DEFAULT NULL, series_id INT DEFAULT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(120) NOT NULL, short_hash VARCHAR(8) NOT NULL, lede LONGTEXT DEFAULT NULL, excerpt LONGTEXT DEFAULT NULL, cover_image_path VARCHAR(255) DEFAULT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', reading_time_min INT NOT NULL, is_featured TINYINT(1) NOT NULL, is_unlisted TINYINT(1) NOT NULL, preview_token VARCHAR(64) NOT NULL, seo_title VARCHAR(255) DEFAULT NULL, seo_description VARCHAR(160) DEFAULT NULL, og_image_path VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_CHRONICLE_ENTRY_SLUG (slug), UNIQUE INDEX UNIQ_CHRONICLE_ENTRY_HASH (short_hash), INDEX IDX_CHRONICLE_ENTRY_ERA (era_id), INDEX IDX_CHRONICLE_ENTRY_SERIES (series_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE chronicle_entry_tag (chronicle_entry_id INT NOT NULL, chronicle_tag_id INT NOT NULL, INDEX IDX_CHRONICLE_ENTRY_TAG_ENTRY (chronicle_entry_id), INDEX IDX_CHRONICLE_ENTRY_TAG_TAG (chronicle_tag_id), PRIMARY KEY(chronicle_entry_id, chronicle_tag_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE chronicle_block (id INT AUTO_INCREMENT NOT NULL, entry_id INT NOT NULL, type VARCHAR(32) NOT NULL, sort_order INT NOT NULL, body LONGTEXT DEFAULT NULL, heading_level INT NOT NULL, image_path VARCHAR(255) DEFAULT NULL, caption VARCHAR(240) DEFAULT NULL, alt VARCHAR(240) DEFAULT NULL, om_track_slug VARCHAR(120) DEFAULT NULL, video_url VARCHAR(500) DEFAULT NULL, video_title VARCHAR(160) DEFAULT NULL, author VARCHAR(120) DEFAULT NULL, callout_style VARCHAR(32) DEFAULT NULL, INDEX IDX_CHRONICLE_BLOCK_ENTRY (entry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE chronicle_block_image (id INT AUTO_INCREMENT NOT NULL, block_id INT NOT NULL, image_path VARCHAR(255) NOT NULL, caption VARCHAR(240) DEFAULT NULL, alt VARCHAR(240) DEFAULT NULL, sort_order INT NOT NULL, INDEX IDX_CHRONICLE_BLOCK_IMAGE_BLOCK (block_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE chronicle_entry ADD CONSTRAINT FK_CHRONICLE_ENTRY_ERA FOREIGN KEY (era_id) REFERENCES chronicle_era (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE chronicle_entry ADD CONSTRAINT FK_CHRONICLE_ENTRY_SERIES FOREIGN KEY (series_id) REFERENCES chronicle_series (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE chronicle_entry_tag ADD CONSTRAINT FK_CHRONICLE_ENTRY_TAG_ENTRY FOREIGN KEY (chronicle_entry_id) REFERENCES chronicle_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chronicle_entry_tag ADD CONSTRAINT FK_CHRONICLE_ENTRY_TAG_TAG FOREIGN KEY (chronicle_tag_id) REFERENCES chronicle_tag (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chronicle_block ADD CONSTRAINT FK_CHRONICLE_BLOCK_ENTRY FOREIGN KEY (entry_id) REFERENCES chronicle_entry (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE chronicle_block_image ADD CONSTRAINT FK_CHRONICLE_BLOCK_IMAGE_BLOCK FOREIGN KEY (block_id) REFERENCES chronicle_block (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry DROP FOREIGN KEY FK_CHRONICLE_ENTRY_ERA');
        $this->addSql('ALTER TABLE chronicle_entry DROP FOREIGN KEY FK_CHRONICLE_ENTRY_SERIES');
        $this->addSql('ALTER TABLE chronicle_entry_tag DROP FOREIGN KEY FK_CHRONICLE_ENTRY_TAG_ENTRY');
        $this->addSql('ALTER TABLE chronicle_entry_tag DROP FOREIGN KEY FK_CHRONICLE_ENTRY_TAG_TAG');
        $this->addSql('ALTER TABLE chronicle_block DROP FOREIGN KEY FK_CHRONICLE_BLOCK_ENTRY');
        $this->addSql('ALTER TABLE chronicle_block_image DROP FOREIGN KEY FK_CHRONICLE_BLOCK_IMAGE_BLOCK');
        $this->addSql('DROP TABLE chronicle_era');
        $this->addSql('DROP TABLE chronicle_series');
        $this->addSql('DROP TABLE chronicle_tag');
        $this->addSql('DROP TABLE chronicle_entry');
        $this->addSql('DROP TABLE chronicle_entry_tag');
        $this->addSql('DROP TABLE chronicle_block');
        $this->addSql('DROP TABLE chronicle_block_image');
    }
}
