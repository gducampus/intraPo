<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260305230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create member table with sector relationship';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE member (
            id INT AUTO_INCREMENT NOT NULL,
            sector_id INT DEFAULT NULL,
            rgpd_consent TINYINT(1) DEFAULT NULL,
            short_title VARCHAR(60) DEFAULT NULL,
            last_name_or_company VARCHAR(180) DEFAULT NULL,
            birth_name VARCHAR(180) DEFAULT NULL,
            first_name_or_service VARCHAR(180) DEFAULT NULL,
            address LONGTEXT DEFAULT NULL,
            postal_code VARCHAR(20) DEFAULT NULL,
            city VARCHAR(120) DEFAULT NULL,
            home_phone VARCHAR(40) DEFAULT NULL,
            mobile_phone VARCHAR(40) DEFAULT NULL,
            preferred_email VARCHAR(180) DEFAULT NULL,
            birth_or_founded_at DATE DEFAULT NULL,
            baptism_at DATE DEFAULT NULL,
            INDEX IDX_97AB2FC4F639F774 (sector_id),
            INDEX idx_member_email (preferred_email),
            INDEX idx_member_last_first (last_name_or_company, first_name_or_service),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE member ADD CONSTRAINT FK_97AB2FC4F639F774 FOREIGN KEY (sector_id) REFERENCES secteur (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member DROP FOREIGN KEY FK_97AB2FC4F639F774');
        $this->addSql('DROP TABLE member');
    }
}

