<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Site users for library auth; move reading progress/bookmarks off admin_user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE site_user (id INT AUTO_INCREMENT NOT NULL, public_id VARCHAR(36) NOT NULL, email VARCHAR(180) DEFAULT NULL, telegram_id BIGINT DEFAULT NULL, telegram_username VARCHAR(64) DEFAULT NULL, display_name VARCHAR(120) NOT NULL, roles JSON NOT NULL, webauthn_user_handle VARCHAR(36) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_login_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX UNIQ_B1B4C6E3B5B48C6 (public_id), UNIQUE INDEX UNIQ_B1B4C6E3E7927C74 (email), UNIQUE INDEX UNIQ_B1B4C6E3CC0DE515 (telegram_id), UNIQUE INDEX UNIQ_B1B4C6E36BACD77E (webauthn_user_handle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE site_login_challenge (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, code_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', consumed_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', attempts INT NOT NULL, INDEX idx_site_login_challenge_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE reading_progress ADD site_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reading_bookmark ADD site_user_id INT DEFAULT NULL');

        // One SiteUser per admin that already has reading data; reuse admin email.
        $this->addSql(<<<'SQL'
INSERT INTO site_user (public_id, email, telegram_id, telegram_username, display_name, roles, webauthn_user_handle, created_at, last_login_at)
SELECT UUID(), a.email, NULL, NULL, a.email, JSON_ARRAY(), UUID(), NOW(), NULL
FROM admin_user a
WHERE a.id IN (
    SELECT DISTINCT admin_user_id FROM reading_progress
    UNION
    SELECT DISTINCT admin_user_id FROM reading_bookmark
)
SQL);

        $this->addSql(<<<'SQL'
UPDATE reading_progress rp
INNER JOIN admin_user a ON a.id = rp.admin_user_id
INNER JOIN site_user s ON s.email = a.email
SET rp.site_user_id = s.id
SQL);
        $this->addSql(<<<'SQL'
UPDATE reading_bookmark rb
INNER JOIN admin_user a ON a.id = rb.admin_user_id
INNER JOIN site_user s ON s.email = a.email
SET rb.site_user_id = s.id
SQL);

        $this->addSql('DELETE FROM reading_progress WHERE site_user_id IS NULL');
        $this->addSql('DELETE FROM reading_bookmark WHERE site_user_id IS NULL');

        $this->addSql('ALTER TABLE reading_progress DROP FOREIGN KEY FK_22C02842A76ED395');
        $this->addSql('DROP INDEX IDX_22C02842A76ED395 ON reading_progress');
        $this->addSql('DROP INDEX uniq_reading_progress_user_book ON reading_progress');
        $this->addSql('ALTER TABLE reading_progress DROP admin_user_id');
        $this->addSql('ALTER TABLE reading_progress MODIFY site_user_id INT NOT NULL');
        $this->addSql('ALTER TABLE reading_progress ADD CONSTRAINT FK_22C02842A793D812 FOREIGN KEY (site_user_id) REFERENCES site_user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_22C02842A793D812 ON reading_progress (site_user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_reading_progress_user_book ON reading_progress (site_user_id, book_id)');

        $this->addSql('ALTER TABLE reading_bookmark DROP FOREIGN KEY FK_2D66B99AA76ED395');
        $this->addSql('DROP INDEX IDX_2D66B99AA76ED395 ON reading_bookmark');
        $this->addSql('DROP INDEX uniq_reading_bookmark_locator ON reading_bookmark');
        $this->addSql('ALTER TABLE reading_bookmark DROP admin_user_id');
        $this->addSql('ALTER TABLE reading_bookmark MODIFY site_user_id INT NOT NULL');
        $this->addSql('ALTER TABLE reading_bookmark ADD CONSTRAINT FK_2D66B99AA793D812 FOREIGN KEY (site_user_id) REFERENCES site_user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_2D66B99AA793D812 ON reading_bookmark (site_user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_reading_bookmark_locator ON reading_bookmark (site_user_id, book_id, chapter_id, block_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reading_progress ADD admin_user_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reading_bookmark ADD admin_user_id INT DEFAULT NULL');

        $this->addSql(<<<'SQL'
UPDATE reading_progress rp
INNER JOIN site_user s ON s.id = rp.site_user_id
INNER JOIN admin_user a ON a.email = s.email
SET rp.admin_user_id = a.id
SQL);
        $this->addSql(<<<'SQL'
UPDATE reading_bookmark rb
INNER JOIN site_user s ON s.id = rb.site_user_id
INNER JOIN admin_user a ON a.email = s.email
SET rb.admin_user_id = a.id
SQL);

        $this->addSql('DELETE FROM reading_progress WHERE admin_user_id IS NULL');
        $this->addSql('DELETE FROM reading_bookmark WHERE admin_user_id IS NULL');

        $this->addSql('ALTER TABLE reading_progress DROP FOREIGN KEY FK_22C02842A793D812');
        $this->addSql('DROP INDEX IDX_22C02842A793D812 ON reading_progress');
        $this->addSql('DROP INDEX uniq_reading_progress_user_book ON reading_progress');
        $this->addSql('ALTER TABLE reading_progress DROP site_user_id');
        $this->addSql('ALTER TABLE reading_progress MODIFY admin_user_id INT NOT NULL');
        $this->addSql('ALTER TABLE reading_progress ADD CONSTRAINT FK_22C02842A76ED395 FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_22C02842A76ED395 ON reading_progress (admin_user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_reading_progress_user_book ON reading_progress (admin_user_id, book_id)');

        $this->addSql('ALTER TABLE reading_bookmark DROP FOREIGN KEY FK_2D66B99AA793D812');
        $this->addSql('DROP INDEX IDX_2D66B99AA793D812 ON reading_bookmark');
        $this->addSql('DROP INDEX uniq_reading_bookmark_locator ON reading_bookmark');
        $this->addSql('ALTER TABLE reading_bookmark DROP site_user_id');
        $this->addSql('ALTER TABLE reading_bookmark MODIFY admin_user_id INT NOT NULL');
        $this->addSql('ALTER TABLE reading_bookmark ADD CONSTRAINT FK_2D66B99AA76ED395 FOREIGN KEY (admin_user_id) REFERENCES admin_user (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_2D66B99AA76ED395 ON reading_bookmark (admin_user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_reading_bookmark_locator ON reading_bookmark (admin_user_id, book_id, chapter_id, block_id)');

        $this->addSql('DROP TABLE site_login_challenge');
        $this->addSql('DROP TABLE site_user');
    }
}
