<?php

namespace App\Service;

use App\Dto\UserRegistrationRequest;
use App\Dto\UserResponse;
use App\Entity\User;
use App\Exception\ConflictException;
use App\Exception\ResourceNotFoundException;
use App\Exception\ValidationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Service for handling user-related operations.
 */
readonly class UserService
{
    public function __construct(
        private EntityManagerInterface      $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface          $validator
    ) {
    }

    /**
     * Get user information as a DTO.
     */
    public function getUserInfo(User $user): UserResponse
    {
        return UserResponse::fromEntity($user);
    }

    /**
     * Find a user by ID.
     *
     * @throws ResourceNotFoundException If the user is not found
     */
    public function findUserById(int $id): User
    {
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            throw new ResourceNotFoundException('User', $id);
        }

        return $user;
    }

    /**
     * Register a new user.
     *
     * @param array $userData User data from the request
     * @return User The newly created user
     * @throws ValidationException If validation fails
     * @throws ConflictException If the email is already in use
     */
    public function registerUser(array $userData): User
    {
        $userDto = UserRegistrationRequest::fromArray($userData);

        $violations = $this->validator->validate($userDto);
        if (count($violations) > 0) {
            throw ValidationException::fromConstraintViolations($violations);
        }

        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userDto->getEmail()]);
        if ($existingUser) {
            throw ConflictException::duplicateEmail($userDto->getEmail());
        }

        $user = new User();
        $user->setEmail($userDto->getEmail());
        $user->setFirstName($userDto->getFirstName());
        $user->setLastName($userDto->getLastName());
        $user->setPassword($this->passwordHasher->hashPassword($user, $userDto->getPlainPassword()));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
