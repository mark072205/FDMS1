<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ProjectRepository;
use App\Repository\CategoryRepository;
use App\Repository\UsersRepository;

final class DashboardController extends AbstractController
{
    #[Route('/admin', name: 'app_dashboard')]
    public function index(
        ProjectRepository $projectRepository,
        CategoryRepository $categoryRepository,
        UsersRepository $usersRepository
    ): Response
    {
        $totalProjects = (int) $projectRepository->count([]);
        $totalCategories = (int) $categoryRepository->count([]);
        $totalUsers = (int) $usersRepository->count([]);

        $completedProjects = (int) $projectRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :completed')
            ->setParameter('completed', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $ongoingProjects = (int) $projectRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :ongoing')
            ->setParameter('ongoing', 'ongoing')
            ->getQuery()
            ->getSingleScalarResult();

        $recentProjects = $projectRepository
            ->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // User Activity Summary
        $usersByRole = $usersRepository
            ->createQueryBuilder('u')
            ->select('u.role, COUNT(u.id) as count')
            ->groupBy('u.role')
            ->getQuery()
            ->getResult();

        $recentLogins = $usersRepository
            ->createQueryBuilder('u')
            ->andWhere('u.lastLogin IS NOT NULL')
            ->orderBy('u.lastLogin', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $activeUsers = (int) $usersRepository
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.lastActivity >= :weekAgo')
            ->setParameter('weekAgo', new \DateTime('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        $pendingVerifications = (int) $usersRepository
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.verified = :false')
            ->setParameter('false', false)
            ->getQuery()
            ->getSingleScalarResult();

        $response = $this->render('admin/dashboard/index.html.twig', [
            'totalProjects' => $totalProjects,
            'totalCategories' => $totalCategories,
            'totalUsers' => $totalUsers,
            'completedProjects' => $completedProjects,
            'ongoingProjects' => $ongoingProjects,
            'recentProjects' => $recentProjects,
            // User Activity Summary
            'usersByRole' => $usersByRole,
            'recentLogins' => $recentLogins,
            'activeUsers' => $activeUsers,
            'pendingVerifications' => $pendingVerifications,
        ]);
        
        // Prevent caching to avoid back button issues after logout
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }
}
