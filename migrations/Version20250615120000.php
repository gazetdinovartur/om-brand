<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250615120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for landing, inquiries, payments and content';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_AD8A54A9E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE case_study (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(120) NOT NULL, summary LONGTEXT DEFAULT NULL, content LONGTEXT DEFAULT NULL, cover_image_path VARCHAR(255) DEFAULT NULL, is_published TINYINT(1) NOT NULL, sort_order INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_6C0F5B98989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE content_block (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(64) NOT NULL, type VARCHAR(255) NOT NULL, title VARCHAR(255) DEFAULT NULL, subtitle VARCHAR(255) DEFAULT NULL, body LONGTEXT DEFAULT NULL, items JSON DEFAULT NULL, sort_order INT NOT NULL, is_visible TINYINT(1) NOT NULL, UNIQUE INDEX UNIQ_29B2EBC5989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inquiry (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL COMMENT \'(DC2Type:uuid)\', name VARCHAR(120) NOT NULL, contact VARCHAR(255) NOT NULL, contact_type VARCHAR(255) NOT NULL, inquiry_type VARCHAR(255) NOT NULL, message LONGTEXT NOT NULL, status VARCHAR(255) NOT NULL, attachment_path VARCHAR(255) DEFAULT NULL, attachment_original_name VARCHAR(255) DEFAULT NULL, attachment_mime_type VARCHAR(100) DEFAULT NULL, admin_note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_5AF3BF60D17F50A6 (uuid), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE payment_offer (id INT AUTO_INCREMENT NOT NULL, inquiry_id INT NOT NULL, token VARCHAR(64) NOT NULL, title VARCHAR(255) NOT NULL, amount INT NOT NULL, sber_payment_url VARCHAR(500) DEFAULT NULL, status VARCHAR(255) NOT NULL, paid_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_98485A475F37A13B (token), INDEX IDX_98485A47A7AD6EEF (inquiry_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE site_settings (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, tagline VARCHAR(255) DEFAULT NULL, city VARCHAR(120) DEFAULT NULL, avatar_path VARCHAR(255) DEFAULT NULL, telegram_url VARCHAR(255) DEFAULT NULL, github_url VARCHAR(255) DEFAULT NULL, email VARCHAR(255) DEFAULT NULL, form_success_message LONGTEXT DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE payment_offer ADD CONSTRAINT FK_98485A47A7AD6EEF FOREIGN KEY (inquiry_id) REFERENCES inquiry (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_offer DROP FOREIGN KEY FK_98485A47A7AD6EEF');
        $this->addSql('DROP TABLE admin_user');
        $this->addSql('DROP TABLE case_study');
        $this->addSql('DROP TABLE content_block');
        $this->addSql('DROP TABLE inquiry');
        $this->addSql('DROP TABLE payment_offer');
        $this->addSql('DROP TABLE site_settings');
    }
}
