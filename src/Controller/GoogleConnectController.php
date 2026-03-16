<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class GoogleConnectController extends AbstractController
{
    #[Route('/connect/google', name: 'connect_google_start')]
    public function connect(Request $request, ClientRegistry $clientRegistry): RedirectResponse
    {
        $mode = (string) $request->query->get('mode', '');
        if (in_array($mode, ['login', 'signup'], true) && $request->hasSession()) {
            $request->getSession()->set('oauth_mode', $mode);
        }

        $role = (string) $request->query->get('role', '');
        if (in_array($role, ['client', 'designer'], true) && $request->hasSession()) {
            $request->getSession()->set('oauth_signup_role', $role);
        }

        return $clientRegistry
            ->getClient('google')
            ->redirect(['email', 'profile'], []);
    }

    #[Route('/connect/google/check', name: 'connect_google_check')]
    public function connectCheck(): void
    {
        throw new \LogicException('This route is handled by the GoogleAuthenticator.');
    }
}

