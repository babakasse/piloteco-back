<?php

namespace App\DataFixtures;

use App\Entity\Company;
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
    }
}