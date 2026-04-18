<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306021000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add extended member columns from Prodon/Glide export';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member
            ADD modification_to_apply LONGTEXT DEFAULT NULL,
            ADD remarks LONGTEXT DEFAULT NULL,
            ADD last_contact_name VARCHAR(180) DEFAULT NULL,
            ADD contact_channel VARCHAR(80) DEFAULT NULL,
            ADD last_contact_at DATE DEFAULT NULL,
            ADD latitude DOUBLE PRECISION DEFAULT NULL,
            ADD longitude DOUBLE PRECISION DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE member
            DROP modification_to_apply,
            DROP remarks,
            DROP last_contact_name,
            DROP contact_channel,
            DROP last_contact_at,
            DROP latitude,
            DROP longitude');
    }
}

