<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250616120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store privacy consent timestamp on inquiries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inquiry ADD privacy_consent_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inquiry DROP privacy_consent_at');
    }
}
