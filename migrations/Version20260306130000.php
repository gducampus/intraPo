<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create trusted devices table for admin approval workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE trusted_device (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT NOT NULL,
            approved_by_id INT DEFAULT NULL,
            device_hash VARCHAR(64) NOT NULL,
            label VARCHAR(180) DEFAULT NULL,
            user_agent LONGTEXT DEFAULT NULL,
            first_ip VARCHAR(45) DEFAULT NULL,
            last_ip VARCHAR(45) DEFAULT NULL,
            is_approved TINYINT(1) NOT NULL,
            requested_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            approved_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            last_seen_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_15CF6A18A76ED395 (user_id),
            INDEX IDX_15CF6A18882AAE07 (approved_by_id),
            INDEX idx_trusted_device_requested_at (requested_at),
            UNIQUE INDEX uniq_trusted_device_user_hash (user_id, device_hash),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE trusted_device ADD CONSTRAINT FK_15CF6A18A76ED395 FOREIGN KEY (user_id) REFERENCES po_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trusted_device ADD CONSTRAINT FK_15CF6A18882AAE07 FOREIGN KEY (approved_by_id) REFERENCES po_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trusted_device DROP FOREIGN KEY FK_15CF6A18A76ED395');
        $this->addSql('ALTER TABLE trusted_device DROP FOREIGN KEY FK_15CF6A18882AAE07');
        $this->addSql('DROP TABLE trusted_device');
    }
}
