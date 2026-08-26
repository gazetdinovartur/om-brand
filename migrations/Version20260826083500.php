<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260826083500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Raise the hourly rate on the development landing page';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            "UPDATE content_block SET items = REPLACE(items, 'от 1500 ₽/ч', 'от 2000 ₽/ч') WHERE slug = 'pricing' AND items LIKE '%от 1500 ₽/ч%'",
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            "UPDATE content_block SET items = REPLACE(items, 'от 2000 ₽/ч', 'от 1500 ₽/ч') WHERE slug = 'pricing' AND items LIKE '%от 2000 ₽/ч%'",
        );
    }
}
