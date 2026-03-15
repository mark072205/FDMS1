<?php

namespace App\Repository;

use App\Entity\Users;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Users>
 */
class UsersRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Users::class);
    }

    /**
     * Find users by role
     * 
     * @return Users[]
     */
    public function findByRole(string $role): array
    {
        // Map Symfony role to database role field
        $roleMap = [
            'ROLE_ADMIN' => 'admin',
            'ROLE_DESIGNER' => 'designer',
            'ROLE_CLIENT' => 'client',
            'staff' => 'staff',
        ];
        
        $dbRole = $roleMap[$role] ?? $role;
        
        return $this->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', $dbRole)
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();
    }
}
