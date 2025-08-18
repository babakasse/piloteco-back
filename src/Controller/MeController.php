<?php

namespace App\Controller;

use App\Exception\ResourceNotFoundException;
use App\Service\UserService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MeController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService
    ) {
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function __invoke(Security $security): JsonResponse
    {
        $user = $security->getUser();

        if (!$user) {
            throw new ResourceNotFoundException('User', 'current');
        }

        $userResponse = $this->userService->getUserInfo($user);
        return new JsonResponse($userResponse->toArray());
    }
}
