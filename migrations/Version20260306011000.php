<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306011000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add name column to po_user';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE po_user ADD name VARCHAR(140) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE po_user DROP name');
    }
}

