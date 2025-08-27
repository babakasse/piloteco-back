<?php

namespace App\DataFixtures;

use App\Entity\CarbonAssessment;
use App\Entity\Company;
use App\Entity\Emission;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class DemoFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Données de démonstration réalistes pour la production
        $companiesData = [
            ['name' => 'EcoTech Solutions', 'sector' => 'Technology', 'address' => '123 Innovation Drive, Paris, France'],
            ['name' => 'Green Manufacturing Co.', 'sector' => 'Industry', 'address' => '456 Industrial Ave, Lyon, France'],
            ['name' => 'Sustainable Logistics', 'sector' => 'Shipping', 'address' => '789 Port Street, Marseille, France'],
            ['name' => 'CleanEnergy Corp', 'sector' => 'Industry', 'address' => '321 Energy Blvd, Nantes, France'],
            ['name' => 'BioCare Health', 'sector' => 'Healthcare', 'address' => '654 Medical Center, Toulouse, France'],
        ];

        // Création des entreprises de démonstration
        $companies = [];
        foreach ($companiesData as $companyData) {
            $company = new Company();
            $company->setName($companyData['name'])
                ->setAddress($companyData['address'])
                ->setSector($companyData['sector']);

            $manager->persist($company);
            $companies[] = $company;
        }

        $manager->flush();

        // Utilisateurs de démonstration avec des mots de passe sécurisés
        $usersData = [
            ['email' => 'demo.admin@piloteco.fr', 'firstName' => 'Admin', 'lastName' => 'Demo', 'roles' => ['ROLE_ADMIN'], 'companyIndex' => 0],
            ['email' => 'demo.manager@ecotech.fr', 'firstName' => 'Marie', 'lastName' => 'Dubois', 'roles' => ['ROLE_USER'], 'companyIndex' => 0],
            ['email' => 'demo.analyst@greenmanuf.fr', 'firstName' => 'Pierre', 'lastName' => 'Martin', 'roles' => ['ROLE_USER'], 'companyIndex' => 1],
            ['email' => 'demo.consultant@sustainable.fr', 'firstName' => 'Sophie', 'lastName' => 'Durand', 'roles' => ['ROLE_USER'], 'companyIndex' => 2],
            ['email' => 'demo.expert@cleanenergy.fr', 'firstName' => 'Thomas', 'lastName' => 'Leroy', 'roles' => ['ROLE_USER'], 'companyIndex' => 3],
        ];

        foreach ($usersData as $userData) {
            $user = new User();
            $user->setEmail($userData['email'])
                ->setFirstName($userData['firstName'])
                ->setLastName($userData['lastName'])
                ->setRoles($userData['roles'])
                ->setCompany($companies[$userData['companyIndex']]);

            // Mot de passe sécurisé pour la démo
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'DemoPassword2024!');
            $user->setPassword($hashedPassword);

            $manager->persist($user);
        }

        $manager->flush();

        // Évaluations carbone réalistes
        $assessmentsData = [
            [
                'name' => 'Bilan Carbone 2023 - EcoTech Solutions',
                'description' => 'Évaluation complète des émissions GES pour l\'année 2023',
                'company' => 0,
                'status' => 'published',
                'date' => '-2 months'
            ],
            [
                'name' => 'Audit Carbone Q4 2023 - Green Manufacturing',
                'description' => 'Audit trimestriel des émissions de la production',
                'company' => 1,
                'status' => 'published',
                'date' => '-1 month'
            ],
            [
                'name' => 'Bilan Carbone Transport 2023 - Sustainable Logistics',
                'description' => 'Analyse des émissions liées au transport et à la logistique',
                'company' => 2,
                'status' => 'draft',
                'date' => '-3 weeks'
            ],
            [
                'name' => 'Évaluation Carbone 2024 - CleanEnergy Corp',
                'description' => 'Bilan carbone préliminaire pour 2024',
                'company' => 3,
                'status' => 'draft',
                'date' => '-1 week'
            ],
        ];

        $assessments = [];
        foreach ($assessmentsData as $assessmentData) {
            $assessment = new CarbonAssessment();
            $assessment->setName($assessmentData['name'])
                ->setDescription($assessmentData['description'])
                ->setAssessmentDate(new \DateTime($assessmentData['date']))
                ->setStatus($assessmentData['status'])
                ->setCompany($companies[$assessmentData['company']]);

            $manager->persist($assessment);
            $assessments[] = $assessment;
        }

        $manager->flush();

        // Émissions réalistes par catégorie
        $emissionsData = [
            // EcoTech Solutions
            [
                ['source' => 'Électricité bureaux', 'category' => 'Electricity', 'amount' => 45.2, 'scope' => 2, 'description' => 'Consommation électrique des bureaux parisiens'],
                ['source' => 'Chauffage gaz naturel', 'category' => 'Heating', 'amount' => 23.8, 'scope' => 1, 'description' => 'Chauffage au gaz naturel des locaux'],
                ['source' => 'Véhicules de fonction', 'category' => 'Transportation', 'amount' => 67.5, 'scope' => 1, 'description' => 'Flotte de véhicules de fonction'],
                ['source' => 'Voyages d\'affaires', 'category' => 'Business Travel', 'amount' => 89.3, 'scope' => 3, 'description' => 'Déplacements professionnels (avion, train)'],
                ['source' => 'Déchets bureaux', 'category' => 'Waste', 'amount' => 12.1, 'scope' => 3, 'description' => 'Gestion des déchets de bureau'],
            ],
            // Green Manufacturing
            [
                ['source' => 'Électricité production', 'category' => 'Electricity', 'amount' => 234.7, 'scope' => 2, 'description' => 'Électricité pour les lignes de production'],
                ['source' => 'Chauffage industriel', 'category' => 'Heating', 'amount' => 156.4, 'scope' => 1, 'description' => 'Chauffage des ateliers de production'],
                ['source' => 'Transport matières premières', 'category' => 'Transportation', 'amount' => 198.9, 'scope' => 3, 'description' => 'Transport des matières premières'],
                ['source' => 'Matières premières', 'category' => 'Materials', 'amount' => 445.6, 'scope' => 3, 'description' => 'Émissions liées aux matières premières'],
                ['source' => 'Déchets industriels', 'category' => 'Waste', 'amount' => 78.2, 'scope' => 3, 'description' => 'Traitement des déchets industriels'],
            ],
            // Sustainable Logistics
            [
                ['source' => 'Flotte transport', 'category' => 'Transportation', 'amount' => 567.8, 'scope' => 1, 'description' => 'Émissions de la flotte de transport'],
                ['source' => 'Carburant entrepôts', 'category' => 'Heating', 'amount' => 89.4, 'scope' => 1, 'description' => 'Chauffage des entrepôts'],
                ['source' => 'Électricité entrepôts', 'category' => 'Electricity', 'amount' => 123.6, 'scope' => 2, 'description' => 'Éclairage et équipements des entrepôts'],
                ['source' => 'Transport sous-traitants', 'category' => 'Transportation', 'amount' => 234.1, 'scope' => 3, 'description' => 'Transport par sous-traitants'],
            ],
            // CleanEnergy Corp
            [
                ['source' => 'Électricité siège', 'category' => 'Electricity', 'amount' => 34.5, 'scope' => 2, 'description' => 'Consommation électrique du siège social'],
                ['source' => 'Véhicules techniques', 'category' => 'Transportation', 'amount' => 45.7, 'scope' => 1, 'description' => 'Véhicules pour interventions techniques'],
                ['source' => 'Équipements IT', 'category' => 'Equipment', 'amount' => 28.9, 'scope' => 3, 'description' => 'Serveurs et équipements informatiques'],
                ['source' => 'Services cloud', 'category' => 'Services', 'amount' => 15.6, 'scope' => 3, 'description' => 'Services cloud et hébergement'],
            ],
        ];

        foreach ($assessments as $index => $assessment) {
            if (isset($emissionsData[$index])) {
                foreach ($emissionsData[$index] as $emissionData) {
                    $emission = new Emission();
                    $emission->setSource($emissionData['source'])
                        ->setCategory($emissionData['category'])
                        ->setDescription($emissionData['description'])
                        ->setAmount($emissionData['amount'])
                        ->setUnit('tCO₂e')
                        ->setScope($emissionData['scope'])
                        ->setAssessment($assessment);

                    $manager->persist($emission);
                }
            }
        }

        $manager->flush();
    }
}
