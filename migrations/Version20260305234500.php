<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260305234500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create role and login history tables';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_role (
            id INT AUTO_INCREMENT NOT NULL,
            code VARCHAR(100) NOT NULL,
            label VARCHAR(120) NOT NULL,
            description LONGTEXT DEFAULT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_app_role_code (code),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('CREATE TABLE login_history (
            id INT AUTO_INCREMENT NOT NULL,
            user_id INT DEFAULT NULL,
            email VARCHAR(180) NOT NULL,
            succeeded TINYINT(1) NOT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent LONGTEXT DEFAULT NULL,
            failure_reason VARCHAR(255) DEFAULT NULL,
            logged_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            INDEX IDX_3C11F06BA76ED395 (user_id),
            INDEX idx_login_history_logged_at (logged_at),
            INDEX idx_login_history_email (email),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');

        $this->addSql('ALTER TABLE login_history ADD CONSTRAINT FK_3C11F06BA76ED395 FOREIGN KEY (user_id) REFERENCES po_user (id) ON DELETE SET NULL');

        $this->addSql("INSERT INTO app_role (code, label, description, created_at) VALUES
            ('ROLE_ADMIN', 'Administrateur', 'Acces complet a l administration.', NOW()),
            ('ROLE_MODULE_ALL', 'Acces tous modules', 'Acces a tous les modules metier.', NOW()),
            ('ROLE_USER', 'Utilisateur', 'Role de base applique automatiquement.', NOW())");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE login_history DROP FOREIGN KEY FK_3C11F06BA76ED395');
        $this->addSql('DROP TABLE login_history');
        $this->addSql('DROP TABLE app_role');
    }
}

