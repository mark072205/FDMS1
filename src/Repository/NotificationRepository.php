<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Find unread notifications for a user
     * 
     * @return Notification[]
     */
    public function findUnreadByUser(Users $user): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent notifications for a user (both read and unread)
     * 
     * @return Notification[]
     */
    public function findRecentByUser(Users $user, int $limit = 20): array
    {
        try {
            return $this->createQueryBuilder('n')
                ->where('n.user = :user')
                ->setParameter('user', $user)
                ->orderBy('n.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            // Return empty array if there's any error
            return [];
        }
    }

    /**
     * Get count of unread notifications for a user
     */
    public function getUnreadCount(Users $user): int
    {
        try {
            $result = $this->createQueryBuilder('n')
                ->select('COUNT(n.id)')
                ->where('n.user = :user')
                ->andWhere('n.isRead = :isRead')
                ->setParameter('user', $user)
                ->setParameter('isRead', false)
                ->getQuery()
                ->getSingleScalarResult();
            
            return (int) $result;
        } catch (\Exception $e) {
            // Return 0 if there's any error (e.g., no results, database issue)
            return 0;
        }
    }

    /**
     * Mark a notification as read
     */
    public function markAsRead(Notification $notification): void
    {
        $notification->setIsRead(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(Users $user): int
    {
        $qb = $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':isRead')
            ->where('n.user = :user')
            ->andWhere('n.isRead = :false')
            ->setParameter('user', $user)
            ->setParameter('isRead', true)
            ->setParameter('false', false);
        
        $result = $qb->getQuery()->execute();
        return (int) $result;
    }

    /**
     * Delete old notifications (older than specified days)
     */
    public function deleteOldNotifications(int $days = 30): int
    {
        $date = new \DateTimeImmutable("-{$days} days");
        
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Find notifications by type for a user
     * 
     * @return Notification[]
     */
    public function findByType(Users $user, string $type, int $limit = 10): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent admin notifications (where user is null - for in-memory admins)
     * 
     * @return Notification[]
     */
    public function findRecentAdminNotifications(int $limit = 20): array
    {
        try {
            return $this->createQueryBuilder('n')
                ->where('n.user IS NULL')
                ->orderBy('n.createdAt', 'DESC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get count of unread admin notifications (where user is null)
     */
    public function getAdminUnreadCount(): int
    {
        try {
            $result = $this->createQueryBuilder('n')
                ->select('COUNT(n.id)')
                ->where('n.user IS NULL')
                ->andWhere('n.isRead = :isRead')
                ->setParameter('isRead', false)
                ->getQuery()
                ->getSingleScalarResult();
            
            return (int) $result;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Mark all admin notifications as read (where user is null)
     */
    public function markAllAdminAsRead(): int
    {
        $qb = $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':isRead')
            ->where('n.user IS NULL')
            ->andWhere('n.isRead = :false')
            ->setParameter('isRead', true)
            ->setParameter('false', false);
        
        $result = $qb->getQuery()->execute();
        return (int) $result;
    }
}

