<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

class LoginAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        // If failure is due to unverified email, send user to check-email page
        $message = $exception instanceof CustomUserMessageAuthenticationException
            ? $exception->getMessage()
            : $exception->getMessageKey();

        if (stripos($message, 'verify your email') !== false) {
            $request->getSession()->getFlashBag()->add(
                'error',
                'Please verify your email address before logging in. Check your inbox for the verification link.'
            );
            return new RedirectResponse($this->urlGenerator->generate('app_signup_check_email'));
        }

        // Default: redirect back to login with error message
        $request->getSession()->getFlashBag()->add(
            'error',
            $exception->getMessage()
        );
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}
