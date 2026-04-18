<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recursive document library folders and files with role-based access';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE document_folder (
            id INT AUTO_INCREMENT NOT NULL,
            parent_id INT DEFAULT NULL,
            name VARCHAR(180) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            position INT NOT NULL,
            is_secured TINYINT(1) NOT NULL,
            access_roles JSON NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_5D9E404A727ACA70 (parent_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document_folder ADD CONSTRAINT FK_5D9E404A727ACA70 FOREIGN KEY (parent_id) REFERENCES document_folder (id) ON DELETE SET NULL');

        $this->addSql('CREATE TABLE document_item (
            id INT AUTO_INCREMENT NOT NULL,
            folder_id INT NOT NULL,
            title VARCHAR(180) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            item_type VARCHAR(30) NOT NULL,
            external_url VARCHAR(800) DEFAULT NULL,
            stored_filename VARCHAR(255) DEFAULT NULL,
            original_filename VARCHAR(255) DEFAULT NULL,
            mime_type VARCHAR(120) DEFAULT NULL,
            file_size INT DEFAULT NULL,
            position INT NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_40B9D9B4162CB942 (folder_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document_item ADD CONSTRAINT FK_40B9D9B4162CB942 FOREIGN KEY (folder_id) REFERENCES document_folder (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_item DROP FOREIGN KEY FK_40B9D9B4162CB942');
        $this->addSql('ALTER TABLE document_folder DROP FOREIGN KEY FK_5D9E404A727ACA70');
        $this->addSql('DROP TABLE document_item');
        $this->addSql('DROP TABLE document_folder');
    }
}
