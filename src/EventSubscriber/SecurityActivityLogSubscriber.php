<?php

namespace App\EventSubscriber;

use App\Entity\Users;
use App\Service\ActivityLogService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\Security\Http\SecurityEvents;

class SecurityActivityLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onLogin',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogin(InteractiveLoginEvent $event): void
    {
        $user = $event->getAuthenticationToken()->getUser();
        $this->activityLogService->logLogin($user instanceof Users ? $user : null);
    }

    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        $this->activityLogService->logLogout($user instanceof Users ? $user : null);
    }
}
