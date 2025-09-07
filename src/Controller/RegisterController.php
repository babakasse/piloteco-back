<?php

namespace App\Controller;

use App\Dto\AuthenticationResponse;
use App\Service\AuthenticationService;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RegisterController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AuthenticationService $authenticationService
    ) {
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function __invoke(Request $request): JsonResponse
    {
        // Désactivation temporaire de l'inscription
        return new JsonResponse([
            'error' => 'Registration is temporarily disabled',
            'message' => 'La création de comptes est temporairement désactivée. Seuls les comptes existants peuvent se connecter.'
        ], 403);

        /* Code d'inscription désactivé temporairement
        $data = json_decode($request->getContent(), true);

        $user = $this->userService->registerUser($data);
        $authResponse = AuthenticationResponse::create(
            $this->authenticationService->generateToken($user),
            $this->authenticationService->getAuthenticatedUserInfo($user)
        );

        return new JsonResponse($authResponse->toArray(), 201);
        */
    }
}
