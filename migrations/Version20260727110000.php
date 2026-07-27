<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Case study year as free-text period (e.g. 2014–2026)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE case_study CHANGE year year VARCHAR(40) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE case_study CHANGE year year INT DEFAULT NULL');
    }
}
