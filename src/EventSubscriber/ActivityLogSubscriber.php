<?php

namespace App\EventSubscriber;

use App\Entity\Users;
use App\Entity\Project;
use App\Entity\Proposal;
use App\Entity\Category;
use App\Entity\File;
use App\Service\ActivityLogService;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

class ActivityLogSubscriber implements EventSubscriber
{
    public function __construct(
        private ActivityLogService $activityLogService
    ) {}

    /**
     * Doctrine event subscriber - returns array of event names
     */
    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        try {
            $entity = $args->getObject();
            
            // Skip logging ActivityLog itself
            if ($entity instanceof \App\Entity\ActivityLog) {
                return;
            }

            $entityType = $this->getEntityType($entity);
            if (!$entityType) {
                return;
            }

            // After persist, entity ID should be available
            $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
            $entityName = $this->getEntityName($entity);
            
            $this->activityLogService->log(
                'CREATE',
                $entityType,
                $entityId,
                "Created {$entityType}: {$entityName}"
            );
        } catch (\Exception $e) {
            // Log error but don't break the main operation
            error_log('ActivityLogSubscriber::postPersist error: ' . $e->getMessage());
        }
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        try {
            $entity = $args->getObject();
            
            // Skip logging ActivityLog itself
            if ($entity instanceof \App\Entity\ActivityLog) {
                return;
            }

            $entityType = $this->getEntityType($entity);
            if (!$entityType) {
                return;
            }

            $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
            $entityName = $this->getEntityName($entity);
            
            $this->activityLogService->log(
                'UPDATE',
                $entityType,
                $entityId,
                "Updated {$entityType}: {$entityName}"
            );
        } catch (\Exception $e) {
            // Log error but don't break the main operation
            error_log('ActivityLogSubscriber::postUpdate error: ' . $e->getMessage());
        }
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        try {
            $entity = $args->getObject();
            
            // Skip logging ActivityLog itself
            if ($entity instanceof \App\Entity\ActivityLog) {
                return;
            }

            $entityType = $this->getEntityType($entity);
            if (!$entityType) {
                return;
            }

            $entityId = method_exists($entity, 'getId') ? $entity->getId() : null;
            $entityName = $this->getEntityName($entity);
            
            $this->activityLogService->log(
                'DELETE',
                $entityType,
                $entityId,
                "Deleted {$entityType}: {$entityName}"
            );
        } catch (\Exception $e) {
            // Log error but don't break the main operation
            error_log('ActivityLogSubscriber::preRemove error: ' . $e->getMessage());
        }
    }

    private function getEntityType($entity): ?string
    {
        return match (true) {
            $entity instanceof Users => 'User',
            $entity instanceof Project => 'Project',
            $entity instanceof Proposal => 'Proposal',
            $entity instanceof Category => 'Category',
            $entity instanceof File => 'File',
            default => null,
        };
    }

    private function getEntityName($entity): string
    {
        if ($entity instanceof Users) {
            return $entity->getUsername() ?? 'Unknown';
        }
        if ($entity instanceof Project) {
            return $entity->getTitle() ?? 'Unknown';
        }
        if ($entity instanceof Proposal) {
            return 'Proposal #' . ($entity->getId() ?? 'Unknown');
        }
        if ($entity instanceof Category) {
            return $entity->getName() ?? 'Unknown';
        }
        if ($entity instanceof File) {
            return $entity->getFilename() ?? 'Unknown';
        }
        
        return 'Unknown';
    }
}
