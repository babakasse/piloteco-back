<?php

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO for user registration requests.
 */
class UserRegistrationRequest
{
    /**
     * @Assert\NotBlank(message="Email is required")
     * @Assert\Email(message="The email '{{ value }}' is not a valid email.")
     */
    private string $email;

    /**
     * @Assert\NotBlank(message="First name is required")
     * @Assert\Length(min=2, max=50, minMessage="First name must be at least {{ limit }} characters long", maxMessage="First name cannot be longer than {{ limit }} characters")
     */
    private string $firstName;

    /**
     * @Assert\NotBlank(message="Last name is required")
     * @Assert\Length(min=2, max=50, minMessage="Last name must be at least {{ limit }} characters long", maxMessage="Last name cannot be longer than {{ limit }} characters")
     */
    private string $lastName;

    /**
     * @Assert\NotBlank(message="Password is required")
     * @Assert\Length(min=8, minMessage="Password must be at least {{ limit }} characters long")
     */
    private string $plainPassword;

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

    public function getPlainPassword(): string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    /**
     * Create a DTO from request data.
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->setEmail($data['email'] ?? '');
        $dto->setFirstName($data['firstName'] ?? '');
        $dto->setLastName($data['lastName'] ?? '');
        $dto->setPlainPassword($data['plainPassword'] ?? '');
        
        return $dto;
    }

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'plainPassword' => $this->plainPassword,
        ];
    }
}