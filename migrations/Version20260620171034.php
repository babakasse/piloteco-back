<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260620171034 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE energy_consumption (id SERIAL NOT NULL, site_id INT NOT NULL, month_year VARCHAR(7) NOT NULL, resource_category VARCHAR(20) NOT NULL, resource_sub_category VARCHAR(50) DEFAULT NULL, food_surface_unit VARCHAR(20) DEFAULT NULL, food_surface_quantity_consumed DOUBLE PRECISION DEFAULT NULL, food_surface_quantity_estimated DOUBLE PRECISION DEFAULT NULL, estimated_food_surface_flag BOOLEAN DEFAULT false NOT NULL, total_surface_unit VARCHAR(20) DEFAULT NULL, total_surface_quantity_consumed DOUBLE PRECISION DEFAULT NULL, total_surface_quantity_estimated DOUBLE PRECISION DEFAULT NULL, estimated_total_surface_flag BOOLEAN DEFAULT false NOT NULL, is_comparable BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_66CFF823F6BD1646 ON energy_consumption (site_id)');
        $this->addSql('CREATE INDEX idx_energy_month_year ON energy_consumption (month_year)');
        $this->addSql('CREATE INDEX idx_energy_resource_category ON energy_consumption (resource_category)');
        $this->addSql('CREATE INDEX idx_energy_site_month_resource ON energy_consumption (site_id, month_year, resource_category)');
        $this->addSql('CREATE UNIQUE INDEX uq_energy_site_month_resource_sub ON energy_consumption (site_id, month_year, resource_category, resource_sub_category)');
        $this->addSql('CREATE TABLE refrigerant_fluid (id SERIAL NOT NULL, site_id INT NOT NULL, month_year VARCHAR(7) NOT NULL, refrigerant_fluid_type VARCHAR(50) NOT NULL, unit_of_measure VARCHAR(20) DEFAULT NULL, quantity_reloaded DOUBLE PRECISION DEFAULT NULL, quantity_estimated DOUBLE PRECISION DEFAULT NULL, estimated_value_flag BOOLEAN DEFAULT false NOT NULL, is_comparable BOOLEAN DEFAULT true NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_F7F75FB9F6BD1646 ON refrigerant_fluid (site_id)');
        $this->addSql('CREATE INDEX idx_refrigerant_month_year ON refrigerant_fluid (month_year)');
        $this->addSql('CREATE INDEX idx_refrigerant_type ON refrigerant_fluid (refrigerant_fluid_type)');
        $this->addSql('CREATE INDEX idx_refrigerant_site_month ON refrigerant_fluid (site_id, month_year)');
        $this->addSql('CREATE UNIQUE INDEX uq_refrigerant_site_month_type ON refrigerant_fluid (site_id, month_year, refrigerant_fluid_type)');
        $this->addSql('CREATE TABLE site (id SERIAL NOT NULL, site_unique_code VARCHAR(50) NOT NULL, country_code VARCHAR(5) NOT NULL, label VARCHAR(100) DEFAULT NULL, site_type VARCHAR(50) DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX idx_site_unique_code ON site (site_unique_code)');
        $this->addSql('CREATE INDEX idx_site_country_code ON site (country_code)');
        $this->addSql('CREATE UNIQUE INDEX uq_site_unique_code ON site (site_unique_code)');
        $this->addSql('CREATE TABLE site_area (id SERIAL NOT NULL, site_id INT NOT NULL, fiscal_year SMALLINT NOT NULL, month SMALLINT NOT NULL, sales_area_m2 DOUBLE PRECISION DEFAULT NULL, total_area_m2 DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE INDEX IDX_EC0E8091F6BD1646 ON site_area (site_id)');
        $this->addSql('CREATE INDEX idx_area_fiscal_year ON site_area (fiscal_year)');
        $this->addSql('CREATE UNIQUE INDEX uq_area_site_year_month ON site_area (site_id, fiscal_year, month)');
        $this->addSql('ALTER TABLE energy_consumption ADD CONSTRAINT FK_66CFF823F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE refrigerant_fluid ADD CONSTRAINT FK_F7F75FB9F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE site_area ADD CONSTRAINT FK_EC0E8091F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE SCHEMA public');
        $this->addSql('ALTER TABLE energy_consumption DROP CONSTRAINT FK_66CFF823F6BD1646');
        $this->addSql('ALTER TABLE refrigerant_fluid DROP CONSTRAINT FK_F7F75FB9F6BD1646');
        $this->addSql('ALTER TABLE site_area DROP CONSTRAINT FK_EC0E8091F6BD1646');
        $this->addSql('DROP TABLE energy_consumption');
        $this->addSql('DROP TABLE refrigerant_fluid');
        $this->addSql('DROP TABLE site');
        $this->addSql('DROP TABLE site_area');
    }
}
