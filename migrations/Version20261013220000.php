<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PaymentOffer: link to SiteUser for practice cabinet';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_offer ADD site_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE payment_offer ADD CONSTRAINT FK_PAYMENT_OFFER_SITE_USER FOREIGN KEY (site_user_id) REFERENCES site_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_PAYMENT_OFFER_SITE_USER ON payment_offer (site_user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_offer DROP FOREIGN KEY FK_PAYMENT_OFFER_SITE_USER');
        $this->addSql('DROP INDEX IDX_PAYMENT_OFFER_SITE_USER ON payment_offer');
        $this->addSql('ALTER TABLE payment_offer DROP site_user_id');
    }
}
