<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add access roles on modules';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE module ADD access_roles JSON DEFAULT NULL');
        $this->addSql("UPDATE module SET access_roles = '[]' WHERE access_roles IS NULL");
        $this->addSql('ALTER TABLE module MODIFY access_roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE module DROP access_roles');
    }
}

