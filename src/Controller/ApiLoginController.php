<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;
use App\Entity\Users;

class ApiLoginController extends AbstractController
{
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(#[CurrentUser] ?Users $user): JsonResponse
    {
        if (null === $user) {
            return $this->json(['message' => 'missing credentials'], 401);
        }

        return $this->json([
            'user' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ]);
    }
}