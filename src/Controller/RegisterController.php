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
        $data = json_decode($request->getContent(), true);

        $user = $this->userService->registerUser($data);
        $authResponse = AuthenticationResponse::create(
            $this->authenticationService->generateToken($user),
            $this->authenticationService->getAuthenticatedUserInfo($user)
        );

        return new JsonResponse($authResponse->toArray(), 201);
    }
}
