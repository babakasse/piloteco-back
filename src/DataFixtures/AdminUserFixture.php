<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminUserFixture extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
//        $user = new User();
//        $user->setEmail('admin@example.com');
//        $user->setRoles(['ROLE_ADMIN']);
//        $user->setFirstName("ADMIN FIRSTNAME");
//        $user->setLastName("ADMIN LASTNAME");
//        $user->setRoles(["ROLE_USER"]);
//        $user->setCompany(1);
//
//        // Hash du mot de passe
//        $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
//        $user->setPassword($hashedPassword);
//
//        $manager->persist($user);
//        $manager->flush();
    }
}
