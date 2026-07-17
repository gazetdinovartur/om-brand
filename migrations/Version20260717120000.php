<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260717120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Extend case studies: landing flags, storytelling, presentation, gallery, SEO';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE case_study ADD show_on_landing TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE case_study ADD is_featured TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE case_study ADD has_detail_page TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE case_study ADD domain VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD role VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD year INT DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD outcome_line VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD story_hook VARCHAR(240) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD story_context LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD story_turning_point LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD story_approach LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD story_body LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD story_outcome LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD story_reflection LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD quote VARCHAR(280) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD quote_author VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD presentation_mode VARCHAR(32) DEFAULT \'none\' NOT NULL');
        $this->addSql('ALTER TABLE case_study ADD video_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD video_title VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD om_track_slug VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD audio_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD audio_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD audio_title VARCHAR(120) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD presentation_duration VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD presentation_intro VARCHAR(200) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD seo_title VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD seo_description VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE case_study ADD og_image_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE case_study SET story_body = content WHERE content IS NOT NULL AND (story_body IS NULL OR story_body = \'\')');
        $this->addSql('UPDATE case_study SET show_on_landing = 1 WHERE is_published = 1');
        $this->addSql('CREATE TABLE case_study_image (id INT AUTO_INCREMENT NOT NULL, case_study_id INT NOT NULL, image_path VARCHAR(255) NOT NULL, caption VARCHAR(255) DEFAULT NULL, sort_order INT NOT NULL, INDEX IDX_CASE_STUDY_IMAGE_CASE (case_study_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE case_study_image ADD CONSTRAINT FK_CASE_STUDY_IMAGE_CASE FOREIGN KEY (case_study_id) REFERENCES case_study (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE case_study_image DROP FOREIGN KEY FK_CASE_STUDY_IMAGE_CASE');
        $this->addSql('DROP TABLE case_study_image');
        $this->addSql('ALTER TABLE case_study DROP show_on_landing, DROP is_featured, DROP has_detail_page, DROP domain, DROP role, DROP year, DROP outcome_line, DROP story_hook, DROP story_context, DROP story_turning_point, DROP story_approach, DROP story_body, DROP story_outcome, DROP story_reflection, DROP quote, DROP quote_author, DROP presentation_mode, DROP video_url, DROP video_title, DROP om_track_slug, DROP audio_path, DROP audio_url, DROP audio_title, DROP presentation_duration, DROP presentation_intro, DROP seo_title, DROP seo_description, DROP og_image_path');
    }
}
