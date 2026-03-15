<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Bundle\SecurityBundle\Security;

class AccessDeniedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private RouterInterface $router,
        private Security $security
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 2],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Only handle AccessDeniedException
        if (!$exception instanceof AccessDeniedException) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Don't convert API auth failures into browser redirects.
        // Let the security layer return a proper 401/403 JSON response.
        if (str_starts_with($path, '/api')) {
            $event->setResponse(new JsonResponse(['message' => 'Access denied'], 403));
            return;
        }

        $user = $this->security->getUser();

        // If user is not authenticated, redirect to login
        if (!$user) {
            $event->setResponse(new RedirectResponse($this->router->generate('app_login')));
            return;
        }

        // Get user roles
        $roles = $user->getRoles();

        // Determine redirect route based on user role
        $route = 'app_landing_page'; // Default fallback

        if (in_array('ROLE_ADMIN', $roles)) {
            $route = 'app_dashboard';
        } elseif (in_array('ROLE_STAFF', $roles)) {
            $route = 'app_dashboard';
        } elseif (in_array('ROLE_DESIGNER', $roles)) {
            $route = 'app_designer_homepage';
        } elseif (in_array('ROLE_CLIENT', $roles)) {
            $route = 'app_client_homepage';
        }

        // Redirect to appropriate page
        $event->setResponse(new RedirectResponse($this->router->generate($route)));
    }
}

