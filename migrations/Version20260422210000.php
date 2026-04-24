<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260422210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add tags to document folders';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE document_tag (
            id INT AUTO_INCREMENT NOT NULL,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(140) NOT NULL,
            created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\',
            UNIQUE INDEX uniq_document_tag_slug (slug),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE document_folder_tag (
            document_folder_id INT NOT NULL,
            document_tag_id INT NOT NULL,
            INDEX IDX_156FBC9D9332A99C (document_folder_id),
            INDEX IDX_156FBC9DD8AB87BF (document_tag_id),
            PRIMARY KEY(document_folder_id, document_tag_id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE document_folder_tag ADD CONSTRAINT FK_156FBC9D9332A99C FOREIGN KEY (document_folder_id) REFERENCES document_folder (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE document_folder_tag ADD CONSTRAINT FK_156FBC9DD8AB87BF FOREIGN KEY (document_tag_id) REFERENCES document_tag (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE document_folder_tag DROP FOREIGN KEY FK_156FBC9D9332A99C');
        $this->addSql('ALTER TABLE document_folder_tag DROP FOREIGN KEY FK_156FBC9DD8AB87BF');
        $this->addSql('DROP TABLE document_folder_tag');
        $this->addSql('DROP TABLE document_tag');
    }
}
