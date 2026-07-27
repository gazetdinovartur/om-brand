<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725233000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Web Push subscriptions and email chronicle subscribers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE push_subscription (id INT AUTO_INCREMENT NOT NULL, endpoint LONGTEXT NOT NULL, endpoint_hash VARCHAR(64) NOT NULL, p256dh VARCHAR(255) NOT NULL, auth VARCHAR(255) NOT NULL, visitor_token VARCHAR(64) DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_seen_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_push_subscription_endpoint_hash (endpoint_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE email_subscriber (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, status VARCHAR(16) NOT NULL, confirm_token VARCHAR(64) NOT NULL, unsubscribe_token VARCHAR(64) NOT NULL, confirmed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX uniq_email_subscriber_email (email), INDEX idx_email_subscriber_confirm (confirm_token), INDEX idx_email_subscriber_unsubscribe (unsubscribe_token), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE push_subscription');
        $this->addSql('DROP TABLE email_subscriber');
    }
}
