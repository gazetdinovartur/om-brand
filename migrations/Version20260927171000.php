<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927171000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Private library books, chapters, reading progress and bookmarks';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE library_book (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, author VARCHAR(180) NOT NULL, slug VARCHAR(120) NOT NULL, language VARCHAR(8) NOT NULL, source_checksum VARCHAR(64) NOT NULL, total_characters INT NOT NULL, footnotes JSON NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_7E43A107989D9B62 (slug), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE library_chapter (id INT AUTO_INCREMENT NOT NULL, book_id INT NOT NULL, title VARCHAR(255) NOT NULL, slug VARCHAR(120) NOT NULL, sort_order INT NOT NULL, blocks JSON NOT NULL, character_count INT NOT NULL, INDEX IDX_3E31A02C16A2B381 (book_id), UNIQUE INDEX uniq_library_chapter_book_slug (book_id, slug), UNIQUE INDEX uniq_library_chapter_book_order (book_id, sort_order), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reading_progress (id INT AUTO_INCREMENT NOT NULL, admin_user_id INT NOT NULL, book_id INT NOT NULL, chapter_id INT NOT NULL, block_id VARCHAR(64) NOT NULL, progress_basis_points INT NOT NULL, last_opened_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_22C02842A76ED395 (admin_user_id), INDEX IDX_22C0284216A2B381 (book_id), INDEX IDX_22C02842C1BD342B (chapter_id), UNIQUE INDEX uniq_reading_progress_user_book (admin_user_id, book_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reading_bookmark (id INT AUTO_INCREMENT NOT NULL, admin_user_id INT NOT NULL, book_id INT NOT NULL, chapter_id INT NOT NULL, block_id VARCHAR(64) NOT NULL, excerpt VARCHAR(280) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_2D66B99AA76ED395 (admin_user_id), INDEX IDX_2D66B99A16A2B381 (book_id), INDEX IDX_2D66B99AC1BD342B (chapter_id), UNIQUE INDEX uniq_reading_bookmark_locator (admin_user_id, book_id, chapter_id, block_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE library_chapter ADD CONSTRAINT FK_3E31A02C16A2B381 FOREIGN KEY (book_id) REFERENCES library_book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_progress ADD CONSTRAINT FK_22C02842A76ED395 FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_progress ADD CONSTRAINT FK_22C0284216A2B381 FOREIGN KEY (book_id) REFERENCES library_book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_progress ADD CONSTRAINT FK_22C02842C1BD342B FOREIGN KEY (chapter_id) REFERENCES library_chapter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_bookmark ADD CONSTRAINT FK_2D66B99AA76ED395 FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_bookmark ADD CONSTRAINT FK_2D66B99A16A2B381 FOREIGN KEY (book_id) REFERENCES library_book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_bookmark ADD CONSTRAINT FK_2D66B99AC1BD342B FOREIGN KEY (chapter_id) REFERENCES library_chapter (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE library_chapter DROP FOREIGN KEY FK_3E31A02C16A2B381');
        $this->addSql('ALTER TABLE reading_progress DROP FOREIGN KEY FK_22C02842A76ED395');
        $this->addSql('ALTER TABLE reading_progress DROP FOREIGN KEY FK_22C0284216A2B381');
        $this->addSql('ALTER TABLE reading_progress DROP FOREIGN KEY FK_22C02842C1BD342B');
        $this->addSql('ALTER TABLE reading_bookmark DROP FOREIGN KEY FK_2D66B99AA76ED395');
        $this->addSql('ALTER TABLE reading_bookmark DROP FOREIGN KEY FK_2D66B99A16A2B381');
        $this->addSql('ALTER TABLE reading_bookmark DROP FOREIGN KEY FK_2D66B99AC1BD342B');
        $this->addSql('DROP TABLE reading_bookmark');
        $this->addSql('DROP TABLE reading_progress');
        $this->addSql('DROP TABLE library_chapter');
        $this->addSql('DROP TABLE library_book');
    }
}
