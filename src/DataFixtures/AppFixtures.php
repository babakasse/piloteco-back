<?php

namespace App\DataFixtures;

use App\Entity\CarbonAssessment;
use App\Entity\Company;
use App\Entity\Emission;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $sectors = ['Industry', 'Automotive', 'Shipping', 'Technology', 'Healthcare', 'Finance', 'Retail', 'Education', 'Construction', 'Hospitality'];

        // Creating companies
        $companies = [];
        for ($i = 1; $i <= 10; $i++) {
            $company = new Company();
            $company->setName("Company $i")
                ->setAddress("123 Street, City $i")
                ->setSector($sectors[($i - 1) % count($sectors)]); // Assure qu'on a toujours un secteur valide

            $manager->persist($company);
            $companies[] = $company;
        }

        // Flush companies first to ensure they have IDs
        $manager->flush();

        // Creating users
        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setEmail("user$i@example.com")
                ->setFirstName("FirstName$i")
                ->setLastName("LastName$i")
                ->setRoles(["ROLE_USER"])
                ->setCompany($companies[($i - 1) % count($companies)]); // Distribution équitable

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);

            $manager->persist($user);
        }

        // Add admin user
        $adminUser = new User();
        $adminUser->setEmail('admin@example.com')
            ->setFirstName("Admin")
            ->setLastName("User")
            ->setRoles(["ROLE_ADMIN"]) // Gardez ROLE_ADMIN, pas ROLE_USER
            ->setCompany($companies[0]); // Première company

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($adminUser, 'password123');
        $adminUser->setPassword($hashedPassword);

        $manager->persist($adminUser);

        $manager->flush();

        // Creating carbon assessments
        $categories = ['Electricity', 'Heating', 'Transportation', 'Business Travel', 'Waste', 'Water', 'Materials', 'Food', 'Services', 'Equipment'];
        $sources = ['Grid Electricity', 'Natural Gas', 'Company Vehicles', 'Air Travel', 'Landfill Waste', 'Water Supply', 'Raw Materials', 'Employee Meals', 'Cloud Services', 'IT Equipment'];
        $unit = 'kgCO₂e';

        $assessments = [];
        foreach ($companies as $index => $company) {
            // Create 1-3 assessments per company
            $numAssessments = rand(1, 3);
            for ($i = 1; $i <= $numAssessments; $i++) {
                $assessment = new CarbonAssessment();
                $assessment->setName("Carbon Assessment $i - " . $company->getName())
                    ->setDescription("Annual carbon footprint assessment for " . $company->getName())
                    ->setAssessmentDate(new \DateTime("-" . rand(1, 12) . " months"))
                    ->setStatus(rand(0, 1) ? 'draft' : 'published')
                    ->setCompany($company);

                $manager->persist($assessment);
                $assessments[] = $assessment;
            }
        }

        // Flush assessments to ensure they have IDs
        $manager->flush();

        // Creating emissions
        foreach ($assessments as $assessment) {
            // Create 5-15 emissions per assessment
            $numEmissions = rand(5, 15);
            for ($i = 1; $i <= $numEmissions; $i++) {
                $emission = new Emission();
                $categoryIndex = rand(0, count($categories) - 1);
                // Valeur aléatoire en kgCO₂e (0.1 à 100)
                $amount = rand(1, 1000) / 10;
                $emission->setSource($sources[$categoryIndex])
                    ->setCategory($categories[$categoryIndex])
                    ->setDescription("Emission from " . $sources[$categoryIndex])
                    ->setAmount($amount)
                    ->setUnit($unit)
                    ->setScope(rand(1, 3)) // Random scope between 1 and 3
                    ->setAssessment($assessment);

                $manager->persist($emission);
            }
        }

        $manager->flush();
    }
}
