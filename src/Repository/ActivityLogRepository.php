<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * Find logs with pagination
     */
    public function findPaginated(
        int $page = 1, 
        int $limit = 50, 
        ?string $action = null, 
        ?string $entityType = null,
        ?string $username = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        $qb = $this->createQueryBuilder('al')
            ->orderBy('al.createdAt', 'ASC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        if ($action) {
            $qb->andWhere('al.action = :action')
                ->setParameter('action', $action);
        }

        if ($entityType) {
            $qb->andWhere('al.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($username) {
            $qb->andWhere('al.username = :username')
                ->setParameter('username', $username);
        }

        if ($dateFrom) {
            try {
                $dateFromObj = new \DateTimeImmutable($dateFrom);
                $qb->andWhere('al.createdAt >= :dateFrom')
                    ->setParameter('dateFrom', $dateFromObj);
            } catch (\Exception $e) {
                // Invalid date, ignore
            }
        }

        if ($dateTo) {
            try {
                $dateToObj = new \DateTimeImmutable($dateTo . ' 23:59:59');
                $qb->andWhere('al.createdAt <= :dateTo')
                    ->setParameter('dateTo', $dateToObj);
            } catch (\Exception $e) {
                // Invalid date, ignore
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count total logs
     */
    public function countLogs(
        ?string $action = null, 
        ?string $entityType = null,
        ?string $username = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int {
        $qb = $this->createQueryBuilder('al')
            ->select('COUNT(al.id)');

        if ($action) {
            $qb->andWhere('al.action = :action')
                ->setParameter('action', $action);
        }

        if ($entityType) {
            $qb->andWhere('al.entityType = :entityType')
                ->setParameter('entityType', $entityType);
        }

        if ($username) {
            $qb->andWhere('al.username = :username')
                ->setParameter('username', $username);
        }

        if ($dateFrom) {
            try {
                $dateFromObj = new \DateTimeImmutable($dateFrom);
                $qb->andWhere('al.createdAt >= :dateFrom')
                    ->setParameter('dateFrom', $dateFromObj);
            } catch (\Exception $e) {
                // Invalid date, ignore
            }
        }

        if ($dateTo) {
            try {
                $dateToObj = new \DateTimeImmutable($dateTo . ' 23:59:59');
                $qb->andWhere('al.createdAt <= :dateTo')
                    ->setParameter('dateTo', $dateToObj);
            } catch (\Exception $e) {
                // Invalid date, ignore
            }
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count logs by action type
     */
    public function countByAction(string $action): int
    {
        return (int) $this->createQueryBuilder('al')
            ->select('COUNT(al.id)')
            ->where('al.action = :action')
            ->setParameter('action', $action)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count logs today
     */
    public function countToday(): int
    {
        $today = new \DateTimeImmutable('today');
        
        return (int) $this->createQueryBuilder('al')
            ->select('COUNT(al.id)')
            ->where('al.createdAt >= :today')
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count logs this week
     */
    public function countThisWeek(): int
    {
        $weekStart = new \DateTimeImmutable('monday this week');
        
        return (int) $this->createQueryBuilder('al')
            ->select('COUNT(al.id)')
            ->where('al.createdAt >= :weekStart')
            ->setParameter('weekStart', $weekStart)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
