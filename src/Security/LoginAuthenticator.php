<?php

namespace App\Security;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginAuthenticator implements UserProviderInterface, AuthenticationSuccessHandlerInterface
{
    private UsersRepository $userRepository;
    private RouterInterface $router;
    private EntityManagerInterface $entityManager;

    public function __construct(
        UsersRepository $userRepository,
        RouterInterface $router,
        EntityManagerInterface $entityManager
    ) {
        $this->userRepository = $userRepository;
        $this->router = $router;
        $this->entityManager = $entityManager;
    }

    // UserProviderInterface methods
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Users) {
            throw new \InvalidArgumentException('User must be an instance of Users');
        }

        // Refresh using email if possible; otherwise fall back to username
        $refreshedUser = $this->userRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        if (!$refreshedUser) {
            $refreshedUser = $this->userRepository->findOneBy(['username' => $user->getUserIdentifier()]);
        }
        
        if (!$refreshedUser) {
            throw new AccountExpiredException('User not found');
        }

        // Check if user is active
        if (!$refreshedUser->isActive()) {
            throw new CustomUserMessageAuthenticationException('Your account has been disabled. Please contact an administrator.');
        }

        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return Users::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Try email first
        $user = $this->userRepository->findOneBy(['email' => $identifier]);

        // If not found by email, try username
        if (!$user) {
            $user = $this->userRepository->findOneBy(['username' => $identifier]);
        }

        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('Your account has been disabled. Please contact an administrator.');
        }

        return $user;
    }

    // AuthenticationSuccessHandlerInterface method
    public function onAuthenticationSuccess(Request $request, TokenInterface $token): RedirectResponse
    {
        $user = $token->getUser();
        $roles = $user->getRoles();

        // Update last login, activity, and updated timestamp for database users
        if ($user instanceof Users) {
            $user->setLastLogin(new \DateTime());
            $user->setLastActivity(new \DateTime());
            $user->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();
            
            // Log login activity
            $activityLogService = $this->entityManager->getRepository(\App\Entity\ActivityLog::class);
            // Login will be logged by ActivityLogSubscriber via InteractiveLoginEvent
        }

        // Redirect based on user role
        if (in_array('ROLE_ADMIN', $roles)) {
            $route = 'app_dashboard';
        } elseif (in_array('ROLE_STAFF', $roles)) {
            // Staff users also go to dashboard (admin area)
            $route = 'app_dashboard';
        } elseif (in_array('ROLE_DESIGNER', $roles)) {
            $route = 'app_designer_homepage';
        } elseif (in_array('ROLE_CLIENT', $roles)) {
            $route = 'app_client_homepage';
        } else {
            // Default fallback
            $route = 'app_landing_page';
        }

        return new RedirectResponse($this->router->generate($route));
    }
}

