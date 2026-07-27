<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260727150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mark vk_crosspost_requested for entries that already have a VK post id';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'UPDATE chronicle_entry SET vk_crosspost_requested = 1 WHERE vk_post_id IS NOT NULL AND vk_crosspost_requested = 0',
        );
    }

    public function down(Schema $schema): void
    {
        // Cannot reliably restore previous checkbox state.
    }
}
