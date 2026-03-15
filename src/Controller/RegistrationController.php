<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use App\Service\EmailVerificationService;
use App\Service\NotificationService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

    #[Route('/signup/check-email', name: 'app_signup_check_email')]
    public function checkEmail(Request $request, UrlGeneratorInterface $urlGenerator): Response
    {
        $verificationUrl = null;
        if ($this->getParameter('kernel.environment') === 'dev') {
            $token = $request->getSession()->remove('dev_verification_token');
            if ($token) {
                $verificationUrl = $urlGenerator->generate('app_verify_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            }
        }
        return $this->render('registration/check_email.html.twig', [
            'verificationUrl' => $verificationUrl,
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
        NotificationService $notificationService,
        EmailVerificationService $emailVerificationService,
        UrlGeneratorInterface $urlGenerator
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

        // Designer and client must verify email before login: set token and send verification email
        $requiresEmailVerification = in_array($role, ['designer', 'client'], true);
        if ($requiresEmailVerification) {
            $token = $emailVerificationService->generateVerificationToken();
            $user->setVerificationToken($token);
            $verificationUrl = $urlGenerator->generate('app_verify_email', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            $logoUrl = $request->getSchemeAndHttpHost() . '/img/logomain.png';
            try {
                $emailVerificationService->sendVerificationEmail($user, $verificationUrl, $logoUrl);
            } catch (\Throwable $e) {
                error_log('[Registration] Verification email failed: ' . $e->getMessage());
                error_log('[Registration] Trace: ' . $e->getTraceAsString());
                return new JsonResponse([
                    'success' => false,
                    'message' => 'We could not send the verification email. Please check your email address is correct and try again, or contact support.',
                    'errors' => ['email' => 'Email delivery failed. Check MAILER_DSN and Brevo SMTP settings. See var/log/dev.log for details.']
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }
        }

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

            // Do not auto-login: designer and client must verify email first
            if (!$requiresEmailVerification) {
                try {
                    $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                    $tokenStorage->setToken($token);
                    $session = $request->getSession();
                    $session->set('_security_main', serialize($token));
                } catch (\Exception $e) {
                    error_log('Auto-login failed: ' . $e->getMessage());
                }
            }

            if ($requiresEmailVerification) {
                $redirectUrl = $this->generateUrl('app_signup_check_email');
                $message = 'Account created! Please check your email to verify your account before logging in.';
                // In dev: pass token in session so check-email page can show a verification link (email may not be sent)
                if ($this->getParameter('kernel.environment') === 'dev') {
                    $request->getSession()->set('dev_verification_token', $token);
                }
            } else {
                $redirectUrl = $role === 'client' ? $this->generateUrl('app_client_homepage') : $this->generateUrl('app_designer_homepage');
                $message = 'Account created successfully!';
            }

            return new JsonResponse([
                'success' => true,
                'message' => $message,
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
