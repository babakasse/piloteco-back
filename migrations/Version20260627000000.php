<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260627000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add site_format column to site table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site ADD site_format VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE site DROP COLUMN site_format');
    }
}
