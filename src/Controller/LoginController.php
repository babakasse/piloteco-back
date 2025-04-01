<?php

namespace App\Controller;

use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class LoginController extends AbstractController
{
    //// *************** USE CODE BELOW TO OVERNIGHT DEFAULT JWT LOGIN *********** //////
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(JWTTokenManagerInterface $jwtManager): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof UserInterface) {
            return $this->json(['error' => 'Invalid credentials'], 401);
        }

        return $this->json([
            'token' => $jwtManager->create($user),
            'username' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }

}
