<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'PaymentOffer: payment_url + yookassa_payment_id (rename from sber_payment_url)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE payment_offer CHANGE sber_payment_url payment_url VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE payment_offer ADD yookassa_payment_id VARCHAR(64) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_payment_offer_yookassa ON payment_offer (yookassa_payment_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_payment_offer_yookassa ON payment_offer');
        $this->addSql('ALTER TABLE payment_offer DROP yookassa_payment_id');
        $this->addSql('ALTER TABLE payment_offer CHANGE payment_url sber_payment_url VARCHAR(500) DEFAULT NULL');
    }
}
