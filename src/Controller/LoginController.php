<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        // Redirect if already logged in based on role
        if ($this->getUser()) {
            // Check user role and redirect accordingly
            if ($this->isGranted('ROLE_ADMIN')) {
                return $this->redirectToRoute('app_dashboard');
            } elseif ($this->isGranted('ROLE_DESIGNER')) {
                return $this->redirectToRoute('app_designer_homepage');
            } elseif ($this->isGranted('ROLE_CLIENT')) {
                return $this->redirectToRoute('app_client_homepage');
            }
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        
        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login/index.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // This method can be blank - it will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
