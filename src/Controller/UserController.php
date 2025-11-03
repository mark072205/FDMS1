<?php

namespace App\Controller;

use App\Entity\Users;
use App\Form\UsersType;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/admin/users')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UsersRepository $usersRepository): Response
    {
        $users = $usersRepository->findAll();
        
        $response = $this->render('admin/user/index.html.twig', [
            'users' => $users,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }


    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(Users $user): Response
    {
        $response = $this->render('admin/user/show.html.twig', [
            'user' => $user,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/{id}/toggle-status', name: 'app_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Users $user, Request $request): JsonResponse
    {
        // Prevent disabling the main admin user
        if ($user->getUsername() === 'admin') {
            return new JsonResponse(['success' => false, 'message' => 'Cannot disable the main admin user.'], 400);
        }

        $user->setIsActive(!$user->isActive());
        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $status = $user->isActive() ? 'enabled' : 'disabled';
        return new JsonResponse(['success' => true, 'message' => "User {$status} successfully.", 'isActive' => $user->isActive()]);
    }

    #[Route('/{id}/reset-password', name: 'app_user_reset_password', methods: ['POST'])]
    public function resetPassword(Users $user, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newPassword = $data['password'] ?? null;

        if (!$newPassword) {
            return new JsonResponse(['success' => false, 'message' => 'Password is required.'], 400);
        }

        // Hash the new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Password reset successfully.']);
    }

    #[Route('/{id}/update-activity', name: 'app_user_update_activity', methods: ['POST'])]
    public function updateActivity(Users $user): JsonResponse
    {
        $user->setLastActivity(new \DateTime());
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Activity updated successfully.']);
    }

    #[Route('/{id}/toggle-verification', name: 'app_user_toggle_verification', methods: ['POST'])]
    public function toggleVerification(Users $user, Request $request): JsonResponse
    {
        // Prevent unverifying the main admin user
        if ($user->getUsername() === 'admin') {
            return new JsonResponse(['success' => false, 'message' => 'Cannot unverify the main admin user.'], 400);
        }

        $user->setVerified(!$user->isVerified());
        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $status = $user->isVerified() ? 'verified' : 'unverified';
        return new JsonResponse(['success' => true, 'message' => "User {$status} successfully.", 'isVerified' => $user->isVerified()]);
    }
}
