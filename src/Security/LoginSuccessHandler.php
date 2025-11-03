<?php

namespace App\Security;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
 

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private RouterInterface $router;
    private EntityManagerInterface $entityManager;

    public function __construct(RouterInterface $router, EntityManagerInterface $entityManager)
    {
        $this->router = $router;
        $this->entityManager = $entityManager;
    }

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
        }

        // Redirect based on user role
        if (in_array('ROLE_ADMIN', $roles)) {
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

