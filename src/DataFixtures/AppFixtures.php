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
        // Creating companies
        $companies = [];
        for ($i = 1; $i <= 3; $i++) {
            $company = new Company();
            $company->setName("Company $i")
                ->setAddress("123 Street, City $i");
            $manager->persist($company);
            $companies[] = $company;
        }

        // Creating users
        for ($i = 1; $i <= 10; $i++) {
            $user = new User();
            $user->setEmail("user$i@example.com")
                ->setFirstName("FirstName$i")
                ->setLastName("LastName$i")
                ->setRoles(["ROLE_USER"])
                ->setCompany($companies[array_rand($companies)]);

            // Hash password
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
            $user->setPassword($hashedPassword);

            $manager->persist($user);
        }

        // Add admin user
        $user = new User();
        $user->setEmail('admin@example.com');
        $user->setRoles(['ROLE_ADMIN']);
        $user->setFirstName("ADMIN FIRSTNAME");
        $user->setLastName("ADMIN LASTNAME");
        $user->setRoles(["ROLE_USER"]);
        $user->setCompany($companies[array_rand($companies)]);

        // Hash password
        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
        $user->setPassword($hashedPassword);
        $manager->persist($user);


        $manager->flush();
    }
}
