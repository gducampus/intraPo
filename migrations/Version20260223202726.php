<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223202726 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE fonctionnalite (id INT AUTO_INCREMENT NOT NULL, label VARCHAR(100) NOT NULL, description LONGTEXT DEFAULT NULL, route_name VARCHAR(100) DEFAULT NULL, url VARCHAR(255) DEFAULT NULL, icon VARCHAR(50) DEFAULT NULL, position INT NOT NULL, is_available TINYINT NOT NULL, module_id INT NOT NULL, INDEX IDX_8F83CB48AFC2B591 (module_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE fonctionnalite ADD CONSTRAINT FK_8F83CB48AFC2B591 FOREIGN KEY (module_id) REFERENCES module (id)');
        $this->addSql('ALTER TABLE po_user CHANGE roles roles JSON NOT NULL, CHANGE email_otp_code email_otp_code VARCHAR(10) DEFAULT NULL, CHANGE email_otp_expires_at email_otp_expires_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE fonctionnalite DROP FOREIGN KEY FK_8F83CB48AFC2B591');
        $this->addSql('DROP TABLE fonctionnalite');
        $this->addSql('ALTER TABLE messenger_messages CHANGE delivered_at delivered_at DATETIME DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE po_user CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`, CHANGE email_otp_code email_otp_code VARCHAR(10) DEFAULT \'NULL\', CHANGE email_otp_expires_at email_otp_expires_at DATETIME DEFAULT \'NULL\'');
    }
}
