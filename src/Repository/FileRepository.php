<?php

namespace App\Repository;

use App\Entity\File;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<File>
 */
class FileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, File::class);
    }

    /**
     * Find files by type
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.type = :type')
            ->andWhere('f.isActive = :active')
            ->setParameter('type', $type)
            ->setParameter('active', true)
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find files by path
     */
    public function findByPath(string $path): ?File
    {
        return $this->createQueryBuilder('f')
            ->where('f.path = :path')
            ->setParameter('path', $path)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find orphaned files (usageCount = 0)
     */
    public function findOrphanedFiles(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.usageCount = 0')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find files in use (usageCount > 0)
     */
    public function findFilesInUse(): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.usageCount > 0')
            ->andWhere('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search files by name or path
     */
    public function searchFiles(string $query): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.filename LIKE :query')
            ->orWhere('f.path LIKE :query')
            ->andWhere('f.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get file statistics
     */
    public function getStatistics(): array
    {
        // Get total count and total size in one query
        $qb = $this->createQueryBuilder('f')
            ->select('COUNT(f.id) as total')
            ->addSelect('SUM(f.size) as totalSize')
            ->where('f.isActive = :active')
            ->setParameter('active', true);

        $result = $qb->getQuery()->getSingleResult();

        $stats = [
            'total' => (int) ($result['total'] ?? 0),
            'totalSize' => (int) ($result['totalSize'] ?? 0),
            'byType' => [],
        ];

        // Get count by type
        $typeResults = $this->createQueryBuilder('f')
            ->select('f.type, COUNT(f.id) as count')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('f.type')
            ->getQuery()
            ->getResult();

        foreach ($typeResults as $result) {
            $stats['byType'][$result['type']] = (int) $result['count'];
        }

        return $stats;
    }
}

