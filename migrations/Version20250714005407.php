<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250714005407 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create carbon assessment and emission tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE "carbon_assessment" (id SERIAL NOT NULL, company_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, assessment_date DATE NOT NULL, year INT DEFAULT NULL, total_emissions DOUBLE PRECISION DEFAULT NULL, scope1_emissions DOUBLE PRECISION DEFAULT NULL, scope2_emissions DOUBLE PRECISION DEFAULT NULL, scope3_emissions DOUBLE PRECISION DEFAULT NULL, status VARCHAR(50) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_AAAE0D01979B1AD6 ON "carbon_assessment" (company_id)');
        $this->addSql('CREATE TABLE "emission" (id SERIAL NOT NULL, assessment_id INT NOT NULL, source VARCHAR(255) NOT NULL, description TEXT DEFAULT NULL, category VARCHAR(100) NOT NULL, amount DOUBLE PRECISION NOT NULL, unit VARCHAR(50) NOT NULL, scope INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F0225CF4DD3DD5F1 ON "emission" (assessment_id)');
        $this->addSql('ALTER TABLE "carbon_assessment" ADD CONSTRAINT FK_AAAE0D01979B1AD6 FOREIGN KEY (company_id) REFERENCES "company" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE "emission" ADD CONSTRAINT FK_F0225CF4DD3DD5F1 FOREIGN KEY (assessment_id) REFERENCES "carbon_assessment" (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE "carbon_assessment" DROP CONSTRAINT FK_AAAE0D01979B1AD6');
        $this->addSql('ALTER TABLE "emission" DROP CONSTRAINT FK_F0225CF4DD3DD5F1');
        $this->addSql('DROP TABLE "carbon_assessment"');
        $this->addSql('DROP TABLE "emission"');
    }
}
