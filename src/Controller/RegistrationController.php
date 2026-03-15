<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class RegistrationController extends AbstractController
{
    #[Route('/signup', name: 'app_signup')]
    public function index(): Response
    {
        return $this->render('registration/register.html.twig', [
            'controller_name' => 'RegistrationController',
            'role' => null,
        ]);
    }

    #[Route('/signup/as-designer', name: 'app_signup_designer')]
    public function asDesigner(): Response
    {
        return $this->render('registration/register.html.twig', [
            'controller_name' => 'RegistrationController',
            'role' => 'designer',
        ]);
    }

    #[Route('/signup/as-client', name: 'app_signup_client')]
    public function asClient(): Response
    {
        return $this->render('registration/register.html.twig', [
            'controller_name' => 'RegistrationController',
            'role' => 'client',
        ]);
    }

    #[Route('/signup/register', name: 'app_signup_register', methods: ['POST'])]
    public function register(
        Request $request, 
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
        UsersRepository $usersRepository,
        UserPasswordHasherInterface $passwordHasher,
        TokenStorageInterface $tokenStorage,
        NotificationService $notificationService
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Get the plain password before validation
        $plainPassword = $data['password'] ?? '';
        $role = $data['role'] ?? 'designer';
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';

        // Prevent admin role from being created in database - admin is in-memory only
        if ($role === 'admin') {
            return new JsonResponse([
                'success' => false,
                'errors' => ['role' => 'Admin users cannot be created through registration. Admin access is managed separately.']
            ], Response::HTTP_BAD_REQUEST);
        }

        // Prevent staff role from being created through public registration - only admins can create staff
        if ($role === 'staff') {
            return new JsonResponse([
                'success' => false,
                'errors' => ['role' => 'Staff users cannot be created through public registration. Staff accounts must be created by administrators.']
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if username already exists
        $existingUserByUsername = $usersRepository->findOneBy(['username' => $username]);
        if ($existingUserByUsername) {
            return new JsonResponse([
                'success' => false,
                'errors' => ['username' => 'This username is already taken. Please choose another one.']
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if email already exists
        $existingUserByEmail = $usersRepository->findOneBy(['email' => $email]);
        if ($existingUserByEmail) {
            return new JsonResponse([
                'success' => false,
                'errors' => ['email' => 'An account with this email already exists.']
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate lastName for clients and designers (required for them, optional for admin/staff)
        if (($role === 'client' || $role === 'designer') && empty($data['lastName'] ?? '')) {
            return new JsonResponse([
                'success' => false,
                'errors' => ['lastName' => 'Last name is required for ' . $role . ' accounts.']
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create new user entity
        $user = new Users();
        $user->setFirstName($data['firstName'] ?? '');
        $user->setLastName($data['lastName'] ?? null); // Can be null for admin/staff
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($plainPassword); // Set plain password for validation
        $user->setRole($role);
        $user->setUserType($role);
        $user->setIsActive(true); // Set to active immediately
        $user->setVerified(false);
        $user->setCreatedAt(new \DateTimeImmutable());

        // Validate the user entity (excluding lastName for admin/staff, excluding password restrictions for admin/staff)
        $validationGroups = ['Default', 'password_required'];
        if ($role !== 'admin' && $role !== 'staff') {
            $validationGroups[] = 'password_strict';
        }
        $errors = $validator->validate($user, null, $validationGroups);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return new JsonResponse([
                'success' => false,
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Hash the password using Symfony's password hasher
        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        // Save the user
        try {
            $entityManager->persist($user);
            $entityManager->flush();

            // Notify admins about new user registration
            try {
                $notificationService->notifyNewUser($user);
            } catch (\Exception $e) {
                // Log error but don't fail registration
                error_log('Failed to send notification for new user: ' . $e->getMessage());
            }

            // Automatically log in the user after registration
            try {
                $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                $tokenStorage->setToken($token);
                
                // Save the token in the session
                $session = $request->getSession();
                $session->set('_security_main', serialize($token));
            } catch (\Exception $e) {
                // If auto-login fails, just log the error but still return success
                error_log('Auto-login failed: ' . $e->getMessage());
            }

            // Determine redirect URL based on role
            $redirectUrl = $role === 'client' ? $this->generateUrl('app_client_homepage') : $this->generateUrl('app_designer_homepage');

            return new JsonResponse([
                'success' => true,
                'message' => 'Account created successfully!',
                'userId' => $user->getId(),
                'role' => $role,
                'redirectUrl' => $redirectUrl
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred while creating your account. Please try again.',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
