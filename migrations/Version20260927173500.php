<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260927173500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'WebAuthn user handles and passkey credential storage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE admin_user ADD webauthn_user_handle VARCHAR(36) DEFAULT NULL');
        $this->addSql('UPDATE admin_user SET webauthn_user_handle = UUID() WHERE webauthn_user_handle IS NULL');
        $this->addSql('ALTER TABLE admin_user MODIFY webauthn_user_handle VARCHAR(36) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AD8A54A96BACD77E ON admin_user (webauthn_user_handle)');

        $this->addSql('CREATE TABLE webauthn_credential (id VARCHAR(36) NOT NULL, label VARCHAR(120) NOT NULL, credential_id_hash VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', last_used_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', public_key_credential_id LONGTEXT NOT NULL COMMENT \'(DC2Type:base64)\', type VARCHAR(255) NOT NULL, transports JSON NOT NULL, attestation_type VARCHAR(255) NOT NULL, trust_path JSON NOT NULL COMMENT \'(DC2Type:trust_path)\', aaguid LONGTEXT NOT NULL COMMENT \'(DC2Type:aaguid)\', credential_public_key LONGTEXT NOT NULL COMMENT \'(DC2Type:base64)\', user_handle VARCHAR(255) NOT NULL, counter INT NOT NULL, other_ui JSON DEFAULT NULL, backup_eligible TINYINT(1) DEFAULT NULL, backup_status TINYINT(1) DEFAULT NULL, uv_initialized TINYINT(1) DEFAULT NULL, UNIQUE INDEX UNIQ_453F035243F36663 (credential_id_hash), INDEX IDX_453F0352989D7A8 (user_handle), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE webauthn_credential');
        $this->addSql('DROP INDEX UNIQ_AD8A54A96BACD77E ON admin_user');
        $this->addSql('ALTER TABLE admin_user DROP webauthn_user_handle');
    }
}
