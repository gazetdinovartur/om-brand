<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260725171235 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop unused chronicle_entry.excerpt (lede covers teaser/SEO fallback)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry DROP excerpt');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE chronicle_entry ADD excerpt LONGTEXT DEFAULT NULL');
    }
}
