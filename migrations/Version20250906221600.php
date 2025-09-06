<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration pour charger les données de démonstration en production
 */
final class Version20250906221600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Chargement des données de démonstration pour la production';
    }

    public function up(Schema $schema): void
    {
        // Ne charger les données de démonstration qu'en production
        if ($_ENV['APP_ENV'] !== 'prod') {
            $this->write('⚠️  Migration ignorée : utilisez les fixtures en local avec "php bin/console doctrine:fixtures:load"');
            return;
        }

        $this->write('🎭 Chargement des données de démonstration pour la production...');

        // Insertion des entreprises de démonstration
        $this->addSql("INSERT INTO company (name, address, sector) VALUES
            ('EcoTech Solutions', '123 Innovation Drive, Paris, France', 'Technology'),
            ('Green Manufacturing Co.', '456 Industrial Ave, Lyon, France', 'Industry'),
            ('Sustainable Logistics', '789 Port Street, Marseille, France', 'Shipping'),
            ('CleanEnergy Corp', '321 Energy Blvd, Nantes, France', 'Industry'),
            ('BioCare Health', '654 Medical Center, Toulouse, France', 'Healthcare')
        ");

        // Insertion des utilisateurs de démonstration
        // Note: Le mot de passe haché correspond à 'DemoPassword2024!'
        $hashedPassword = '$2y$13$Hg8RjD.KqN/LbV8ZqF4XQeJGN7xQ8YmZ2cJWE9.CvR6iP3mF5jJ8K';

        $this->addSql("INSERT INTO \"user\" (email, first_name, last_name, roles, password, company_id, created_at, updated_at) VALUES 
            ('demo.admin@piloteco.fr', 'Admin', 'Demo', '[\"ROLE_ADMIN\"]'::json, '" . $hashedPassword . "', (SELECT id FROM company WHERE name = 'EcoTech Solutions' LIMIT 1), NOW(), NOW()),
            ('demo.manager@ecotech.fr', 'Marie', 'Dubois', '[\"ROLE_USER\"]'::json, '" . $hashedPassword . "', (SELECT id FROM company WHERE name = 'EcoTech Solutions' LIMIT 1), NOW(), NOW()),
            ('demo.analyst@greenmanuf.fr', 'Pierre', 'Martin', '[\"ROLE_USER\"]'::json, '" . $hashedPassword . "', (SELECT id FROM company WHERE name = 'Green Manufacturing Co.' LIMIT 1), NOW(), NOW()),
            ('demo.consultant@sustainable.fr', 'Sophie', 'Durand', '[\"ROLE_USER\"]'::json, '" . $hashedPassword . "', (SELECT id FROM company WHERE name = 'Sustainable Logistics' LIMIT 1), NOW(), NOW()),
            ('demo.expert@cleanenergy.fr', 'Thomas', 'Leroy', '[\"ROLE_USER\"]'::json, '" . $hashedPassword . "', (SELECT id FROM company WHERE name = 'CleanEnergy Corp' LIMIT 1), NOW(), NOW())
        ");

        // Insertion des évaluations carbone
        $this->addSql("INSERT INTO carbon_assessment (name, description, assessment_date, status, company_id) VALUES 
            ('Bilan Carbone 2023 - EcoTech Solutions', 'Évaluation complète des émissions GES pour l''année 2023', NOW() - INTERVAL '2 MONTH', 'published', (SELECT id FROM company WHERE name = 'EcoTech Solutions' LIMIT 1)),
            ('Audit Carbone Q4 2023 - Green Manufacturing', 'Audit trimestriel des émissions de la production', NOW() - INTERVAL '1 MONTH', 'published', (SELECT id FROM company WHERE name = 'Green Manufacturing Co.' LIMIT 1)),
            ('Bilan Carbone Transport 2023 - Sustainable Logistics', 'Analyse des émissions liées au transport et à la logistique', NOW() - INTERVAL '21 DAY', 'draft', (SELECT id FROM company WHERE name = 'Sustainable Logistics' LIMIT 1)),
            ('Évaluation Carbone 2024 - CleanEnergy Corp', 'Bilan carbone préliminaire pour 2024', NOW() - INTERVAL '7 DAY', 'draft', (SELECT id FROM company WHERE name = 'CleanEnergy Corp' LIMIT 1))
        ");

        // Insertion des émissions pour EcoTech Solutions
        $this->addSql("INSERT INTO emission (source, category, amount, scope, description, assessment_id) VALUES 
            ('Électricité bureaux', 'Electricity', 45.2, 2, 'Consommation électrique des bureaux parisiens', (SELECT id FROM carbon_assessment WHERE name = 'Bilan Carbone 2023 - EcoTech Solutions' LIMIT 1)),
            ('Chauffage gaz naturel', 'Heating', 23.8, 1, 'Chauffage au gaz naturel des locaux', (SELECT id FROM carbon_assessment WHERE name = 'Bilan Carbone 2023 - EcoTech Solutions' LIMIT 1)),
            ('Véhicules de fonction', 'Transportation', 67.5, 1, 'Flotte de véhicules de fonction', (SELECT id FROM carbon_assessment WHERE name = 'Bilan Carbone 2023 - EcoTech Solutions' LIMIT 1)),
            ('Voyages d''affaires', 'Business Travel', 89.3, 3, 'Déplacements professionnels (avion, train)', (SELECT id FROM carbon_assessment WHERE name = 'Bilan Carbone 2023 - EcoTech Solutions' LIMIT 1)),
            ('Déchets bureaux', 'Waste', 12.1, 3, 'Gestion des déchets de bureau', (SELECT id FROM carbon_assessment WHERE name = 'Bilan Carbone 2023 - EcoTech Solutions' LIMIT 1))
        ");

        // Insertion des émissions pour Green Manufacturing
        $this->addSql("INSERT INTO emission (source, category, amount, scope, description, assessment_id) VALUES 
            ('Électricité production', 'Electricity', 234.7, 2, 'Électricité pour les lignes de production', (SELECT id FROM carbon_assessment WHERE name = 'Audit Carbone Q4 2023 - Green Manufacturing' LIMIT 1)),
            ('Chauffage industriel', 'Heating', 156.4, 1, 'Chauffage des ateliers de production', (SELECT id FROM carbon_assessment WHERE name = 'Audit Carbone Q4 2023 - Green Manufacturing' LIMIT 1)),
            ('Transport matières premières', 'Transportation', 198.9, 3, 'Transport des matières premières', (SELECT id FROM carbon_assessment WHERE name = 'Audit Carbone Q4 2023 - Green Manufacturing' LIMIT 1)),
            ('Matières premières', 'Materials', 445.6, 3, 'Émissions liées aux matières premières', (SELECT id FROM carbon_assessment WHERE name = 'Audit Carbone Q4 2023 - Green Manufacturing' LIMIT 1)),
            ('Déchets industriels', 'Waste', 78.2, 3, 'Traitement des déchets industriels', (SELECT id FROM carbon_assessment WHERE name = 'Audit Carbone Q4 2023 - Green Manufacturing' LIMIT 1))
        ");

        // Insertion des émissions pour Sustainable Logistics
        $this->addSql("INSERT INTO emission (source, category, amount, scope, description, assessment_id) VALUES 
            ('Flotte transport', 'Transportation', 567.8, 1, 'Émissions de la flotte de transport', (SELECT id FROM carbon_assessment WHERE name = 'Bilan Carbone Transport 2023 - Sustainable Logistics' LIMIT 1)),
            ('Carburant entrepôts', 'Heating', 89.4, 1, 'Chauffage des entrepôts', (SELECT id FROM carbon_assessment WHERE name = 'Bilan Carbone Transport 2023 - Sustainable Logistics' LIMIT 1)),
            ('Électricité entrepôts', 'Electricity', 123.6, 2, 'Éclairage et équipements des entrepôts', (SELECT id FROM carbon_assessment WHERE name = 'Bilan Carbone Transport 2023 - Sustainable Logistics' LIMIT 1)),
            ('Transport sous-traitants', 'Transportation', 234.1, 3, 'Transport par sous-traitants', (SELECT id FROM carbon_assessment WHERE name = 'Bilan Carbone Transport 2023 - Sustainable Logistics' LIMIT 1))
        ");

        // Insertion des émissions pour CleanEnergy Corp
        $this->addSql("INSERT INTO emission (source, category, amount, scope, description, assessment_id) VALUES 
            ('Électricité siège', 'Electricity', 34.5, 2, 'Consommation électrique du siège social', (SELECT id FROM carbon_assessment WHERE name = 'Évaluation Carbone 2024 - CleanEnergy Corp' LIMIT 1)),
            ('Véhicules techniques', 'Transportation', 45.7, 1, 'Véhicules pour interventions techniques', (SELECT id FROM carbon_assessment WHERE name = 'Évaluation Carbone 2024 - CleanEnergy Corp' LIMIT 1)),
            ('Équipements IT', 'Equipment', 28.9, 3, 'Serveurs et équipements informatiques', (SELECT id FROM carbon_assessment WHERE name = 'Évaluation Carbone 2024 - CleanEnergy Corp' LIMIT 1)),
            ('Services cloud', 'Services', 15.6, 3, 'Services cloud et hébergement', (SELECT id FROM carbon_assessment WHERE name = 'Évaluation Carbone 2024 - CleanEnergy Corp' LIMIT 1))
        ");
    }

    public function down(Schema $schema): void
    {
        // Suppression des données de démonstration dans l'ordre inverse
        $this->addSql("DELETE FROM emission WHERE assessment_id IN (
            SELECT id FROM carbon_assessment WHERE name LIKE '%EcoTech Solutions%' 
            OR name LIKE '%Green Manufacturing%' 
            OR name LIKE '%Sustainable Logistics%' 
            OR name LIKE '%CleanEnergy Corp%'
        )");

        $this->addSql("DELETE FROM carbon_assessment WHERE name LIKE '%EcoTech Solutions%' 
            OR name LIKE '%Green Manufacturing%' 
            OR name LIKE '%Sustainable Logistics%' 
            OR name LIKE '%CleanEnergy Corp%'");

        $this->addSql("DELETE FROM \"user\" WHERE email IN (
            'demo.admin@piloteco.fr',
            'demo.manager@ecotech.fr',
            'demo.analyst@greenmanuf.fr',
            'demo.consultant@sustainable.fr',
            'demo.expert@cleanenergy.fr'
        )");

        $this->addSql("DELETE FROM company WHERE name IN (
            'EcoTech Solutions',
            'Green Manufacturing Co.',
            'Sustainable Logistics',
            'CleanEnergy Corp',
            'BioCare Health'
        )");
    }
}
