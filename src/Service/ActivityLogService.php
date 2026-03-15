<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;

class ActivityLogService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Security $security
    ) {}

    /**
     * Log an activity
     */
    public function log(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?string $details = null
    ): void {
        try {
            $user = $this->security->getUser();
            
            $log = new ActivityLog();
            
            if ($user instanceof Users) {
                $log->setUser($user);
                $log->setUsername($user->getUsername());
                $roles = $user->getRoles();
                $log->setRole($roles[0] ?? 'ROLE_USER');
            } else {
                // For in-memory admin users
                $log->setUsername('admin');
                $log->setRole('ROLE_ADMIN');
            }
            
            $log->setAction($action);
            $log->setEntityType($entityType);
            $log->setEntityId($entityId);
            $log->setDetails($details);
            
            $this->entityManager->persist($log);
            // Flush the log entry - postPersist is called after the main flush completes
            // so it's safe to flush here
            $this->entityManager->flush();
        } catch (\Exception $e) {
            // Log error but don't break the main operation
            error_log('ActivityLogService::log error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
        }
    }

    /**
     * Log login action
     */
    public function logLogin(?Users $user = null): void
    {
        $username = 'admin';
        $role = 'ROLE_ADMIN';
        
        if ($user) {
            $username = $user->getUsername();
            $roles = $user->getRoles();
            $role = $roles[0] ?? 'ROLE_USER';
        }
        
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setUsername($username);
        $log->setRole($role);
        $log->setAction('LOGIN');
        $log->setDetails('User logged in');
        
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }

    /**
     * Log logout action
     */
    public function logLogout(?Users $user = null): void
    {
        $username = 'admin';
        $role = 'ROLE_ADMIN';
        
        if ($user) {
            $username = $user->getUsername();
            $roles = $user->getRoles();
            $role = $roles[0] ?? 'ROLE_USER';
        }
        
        $log = new ActivityLog();
        $log->setUser($user);
        $log->setUsername($username);
        $log->setRole($role);
        $log->setAction('LOGOUT');
        $log->setDetails('User logged out');
        
        $this->entityManager->persist($log);
        $this->entityManager->flush();
    }
}
