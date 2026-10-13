<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Library reader: quotes, notes, PDF render mode/source path';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE library_book ADD render_mode VARCHAR(32) NOT NULL DEFAULT \'html\', ADD source_path VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE TABLE reading_quote (id INT AUTO_INCREMENT NOT NULL, site_user_id INT NOT NULL, book_id INT NOT NULL, chapter_id INT NOT NULL, block_id VARCHAR(64) NOT NULL, start_offset INT NOT NULL, end_offset INT NOT NULL, excerpt VARCHAR(400) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_reading_quote_user_book (site_user_id, book_id), INDEX IDX_READING_QUOTE_USER (site_user_id), INDEX IDX_READING_QUOTE_BOOK (book_id), INDEX IDX_READING_QUOTE_CHAPTER (chapter_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE reading_note (id INT AUTO_INCREMENT NOT NULL, site_user_id INT NOT NULL, book_id INT NOT NULL, chapter_id INT NOT NULL, block_id VARCHAR(64) NOT NULL, body LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX idx_reading_note_user_book (site_user_id, book_id), INDEX IDX_READING_NOTE_USER (site_user_id), INDEX IDX_READING_NOTE_BOOK (book_id), INDEX IDX_READING_NOTE_CHAPTER (chapter_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE reading_quote ADD CONSTRAINT FK_READING_QUOTE_USER FOREIGN KEY (site_user_id) REFERENCES site_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_quote ADD CONSTRAINT FK_READING_QUOTE_BOOK FOREIGN KEY (book_id) REFERENCES library_book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_quote ADD CONSTRAINT FK_READING_QUOTE_CHAPTER FOREIGN KEY (chapter_id) REFERENCES library_chapter (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_note ADD CONSTRAINT FK_READING_NOTE_USER FOREIGN KEY (site_user_id) REFERENCES site_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_note ADD CONSTRAINT FK_READING_NOTE_BOOK FOREIGN KEY (book_id) REFERENCES library_book (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reading_note ADD CONSTRAINT FK_READING_NOTE_CHAPTER FOREIGN KEY (chapter_id) REFERENCES library_chapter (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reading_quote DROP FOREIGN KEY FK_READING_QUOTE_USER');
        $this->addSql('ALTER TABLE reading_quote DROP FOREIGN KEY FK_READING_QUOTE_BOOK');
        $this->addSql('ALTER TABLE reading_quote DROP FOREIGN KEY FK_READING_QUOTE_CHAPTER');
        $this->addSql('ALTER TABLE reading_note DROP FOREIGN KEY FK_READING_NOTE_USER');
        $this->addSql('ALTER TABLE reading_note DROP FOREIGN KEY FK_READING_NOTE_BOOK');
        $this->addSql('ALTER TABLE reading_note DROP FOREIGN KEY FK_READING_NOTE_CHAPTER');
        $this->addSql('DROP TABLE reading_quote');
        $this->addSql('DROP TABLE reading_note');
        $this->addSql('ALTER TABLE library_book DROP render_mode, DROP source_path');
    }
}
