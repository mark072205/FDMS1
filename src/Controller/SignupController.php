<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
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

final class SignupController extends AbstractController
{
    #[Route('/signup', name: 'app_signup')]
    public function index(): Response
    {
        return $this->render('signup/index.html.twig', [
            'controller_name' => 'SignupController',
            'role' => null,
        ]);
    }

    #[Route('/signup/as-designer', name: 'app_signup_designer')]
    public function asDesigner(): Response
    {
        return $this->render('signup/index.html.twig', [
            'controller_name' => 'SignupController',
            'role' => 'designer',
        ]);
    }

    #[Route('/signup/as-client', name: 'app_signup_client')]
    public function asClient(): Response
    {
        return $this->render('signup/index.html.twig', [
            'controller_name' => 'SignupController',
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
        TokenStorageInterface $tokenStorage
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Get the plain password before validation
        $plainPassword = $data['password'] ?? '';
        $role = $data['role'] ?? 'designer';
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';

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

        // Create new user entity
        $user = new Users();
        $user->setFirstName($data['firstName'] ?? '');
        $user->setLastName($data['lastName'] ?? '');
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPassword($plainPassword); // Set plain password for validation
        $user->setRole($role);
        $user->setUserType($role);
        $user->setIsActive(true); // Set to active immediately
        $user->setVerified(false);
        $user->setCreatedAt(new \DateTimeImmutable());

        // Validate the user entity
        $errors = $validator->validate($user);

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
