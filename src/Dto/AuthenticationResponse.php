<?php

namespace App\Dto;

/**
 * DTO for authentication responses.
 */
class AuthenticationResponse
{
    private string $token;
    private array $user;

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getUser(): array
    {
        return $this->user;
    }

    public function setUser(array $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Create a DTO from token and user data.
     */
    public static function create(string $token, array $user): self
    {
        $dto = new self();
        $dto->setToken($token);
        $dto->setUser($user);
        
        return $dto;
    }

    /**
     * Convert DTO to array.
     */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'user' => $this->user,
        ];
    }
}