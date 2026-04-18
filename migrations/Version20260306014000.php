<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306014000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize module.access_roles to valid JSON arrays';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE module SET access_roles = '[]' WHERE access_roles IS NULL OR access_roles = '' OR JSON_VALID(access_roles) = 0");
        $this->addSql('ALTER TABLE module CHANGE access_roles access_roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // No rollback needed for data normalization.
    }
}

