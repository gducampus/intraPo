<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260306120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align po_user.email_otp_code length with OTP hash size';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE po_user CHANGE email_otp_code email_otp_code VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE po_user CHANGE email_otp_code email_otp_code VARCHAR(10) DEFAULT NULL');
    }
}
