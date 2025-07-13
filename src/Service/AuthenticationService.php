<?php

namespace App\Service;

use App\Dto\AuthenticationResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Service for handling authentication-related operations.
 */
readonly class AuthenticationService
{
    public function __construct(
        private JWTTokenManagerInterface $jwtManager
    ) {
    }

    /**
     * Generate a JWT token for a user.
     */
    public function generateToken(UserInterface $user): string
    {
        return $this->jwtManager->create($user);
    }

    /**
     * Get basic user information for authentication response.
     */
    public function getAuthenticatedUserInfo(UserInterface $user): array
    {
        return [
            'email' => $user->getUserIdentifier(),
            'roles' => $user->getRoles()
        ];
    }

    /**
     * Create authentication response with token and user info.
     */
    public function createAuthResponse(UserInterface $user): AuthenticationResponse
    {
        return AuthenticationResponse::create(
            $this->generateToken($user),
            $this->getAuthenticatedUserInfo($user)
        );
    }
}
