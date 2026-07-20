<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260720210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chronicle entry source_key for idempotent corpus import; rename tatarcha→tatarstan';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry ADD source_key VARCHAR(160) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CHRONICLE_ENTRY_SOURCE_KEY ON chronicle_entry (source_key)');
        $this->addSql("UPDATE chronicle_era SET slug = 'tatarstan', title = 'Татарстан', period_label = '10.2020–08.2021' WHERE slug = 'tatarcha'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_CHRONICLE_ENTRY_SOURCE_KEY ON chronicle_entry');
        $this->addSql('ALTER TABLE chronicle_entry DROP source_key');
        $this->addSql("UPDATE chronicle_era SET slug = 'tatarcha', title = 'Татарча' WHERE slug = 'tatarstan'");
    }
}
