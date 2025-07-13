<?php

namespace App\Dto;

use App\Entity\User;

/**
 * DTO for user information responses.
 */
class UserResponse
{
    private int $id;
    private string $email;
    private string $firstName;
    private string $lastName;
    private array $roles;
    private ?array $company;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function getCompany(): ?array
    {
        return $this->company;
    }

    public function setCompany(?array $company): self
    {
        $this->company = $company;
        return $this;
    }

    /**
     * Create a DTO from a User entity.
     */
    public static function fromEntity(User $user): self
    {
        $dto = new self();
        $dto->setId($user->getId());
        $dto->setEmail($user->getEmail());
        $dto->setFirstName($user->getFirstName());
        $dto->setLastName($user->getLastName());
        $dto->setRoles($user->getRoles());
        
        if ($user->getCompany()) {
            $dto->setCompany([
                'id' => $user->getCompany()->getId(),
                'name' => $user->getCompany()->getName(),
                'address' => $user->getCompany()->getAddress(),
                'sector' => $user->getCompany()->getSector(),
            ]);
        } else {
            $dto->setCompany(null);
        }
        
        return $dto;
    }

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'roles' => $this->roles,
            'company' => $this->company,
        ];
    }
}