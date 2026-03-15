<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\ProjectRepository;
use App\Repository\CategoryRepository;
use App\Repository\UsersRepository;
use App\Repository\ProposalRepository;
use App\Repository\FileRepository;
use Doctrine\ORM\QueryBuilder;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    public function index(
        ProjectRepository $projectRepository,
        CategoryRepository $categoryRepository,
        UsersRepository $usersRepository,
        ProposalRepository $proposalRepository,
        FileRepository $fileRepository
    ): Response
    {
        $totalProjects = (int) $projectRepository->count([]);
        $totalCategories = (int) $categoryRepository->count([]);
        // Include all users (admin, staff, client, designer)
        $totalUsers = (int) $usersRepository->count([]);
        
        // Count admin users separately
        $totalAdmins = (int) $usersRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.userType = :adminType')
            ->setParameter('adminType', 'admin')
            ->getQuery()
            ->getSingleScalarResult();
        
        // Count staff users separately
        $totalStaff = (int) $usersRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.userType = :staffType')
            ->setParameter('staffType', 'staff')
            ->getQuery()
            ->getSingleScalarResult();

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

        $pendingProjects = (int) $projectRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :pending')
            ->setParameter('pending', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        $activeProjects = (int) $projectRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :active')
            ->setParameter('active', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $cancelledProjects = (int) $projectRepository
            ->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :cancelled')
            ->setParameter('cancelled', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        $recentProjects = $projectRepository
            ->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.client', 'client')
            ->addSelect('client')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // User Activity Summary (include all users)
        $usersByRole = $usersRepository
            ->createQueryBuilder('u')
            ->select('u.role, COUNT(u.id) as count')
            ->groupBy('u.role')
            ->getQuery()
            ->getResult();

        $recentLogins = $usersRepository
            ->createQueryBuilder('u')
            ->where('u.lastLogin IS NOT NULL')
            ->orderBy('u.lastLogin', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $activeUsers = (int) $usersRepository
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.lastActivity >= :weekAgo')
            ->setParameter('weekAgo', new \DateTime('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        $pendingVerifications = (int) $usersRepository
            ->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.verified = :false')
            ->setParameter('false', false)
            ->getQuery()
            ->getSingleScalarResult();

        // Proposal Statistics
        $totalProposals = (int) $proposalRepository->count([]);
        
        $pendingProposals = (int) $proposalRepository
            ->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->andWhere('pr.status = :pending')
            ->setParameter('pending', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        $acceptedProposals = (int) $proposalRepository
            ->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->andWhere('pr.status = :accepted')
            ->setParameter('accepted', 'accepted')
            ->getQuery()
            ->getSingleScalarResult();

        $rejectedProposals = (int) $proposalRepository
            ->createQueryBuilder('pr')
            ->select('COUNT(pr.id)')
            ->andWhere('pr.status = :rejected')
            ->setParameter('rejected', 'rejected')
            ->getQuery()
            ->getSingleScalarResult();

        $recentProposals = $proposalRepository
            ->createQueryBuilder('pr')
            ->leftJoin('pr.project', 'p')
            ->addSelect('p')
            ->leftJoin('pr.designer', 'd')
            ->addSelect('d')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->orderBy('pr.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // File Statistics
        $fileStats = $fileRepository->getStatistics();
        $totalFiles = $fileStats['total'] ?? 0;
        $totalFileSize = $fileStats['totalSize'] ?? 0;
        $totalFileSizeFormatted = $this->formatFileSize($totalFileSize);
        
        $imageFiles = (int) ($fileStats['byType']['image'] ?? 0);
        $documentFiles = (int) ($fileStats['byType']['document'] ?? 0);
        $orphanedFiles = count($fileRepository->findOrphanedFiles());
        $filesInUse = count($fileRepository->findFilesInUse());

        $recentFiles = $fileRepository
            ->createQueryBuilder('f')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.uploadedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Analytics data (last 30 days)
        $startDate = new \DateTimeImmutable('-30 days');
        $endDate = new \DateTimeImmutable('now');
        $periodDays = $startDate->diff($endDate)->days;
        $previousStartDate = $startDate->modify("-{$periodDays} days");
        $previousEndDate = $startDate;

        $analytics = [
            'revenue' => $this->getRevenueAnalytics($startDate, $endDate, $previousStartDate, $previousEndDate, $projectRepository, $proposalRepository),
            'userGrowth' => $this->getUserGrowthAnalytics($startDate, $endDate, $previousStartDate, $previousEndDate, $usersRepository),
            'projectMetrics' => $this->getProjectMetrics($startDate, $endDate, $previousStartDate, $previousEndDate, $projectRepository),
            'timeSeries' => $this->getTimeSeriesData($startDate, $endDate, $projectRepository, $usersRepository, $proposalRepository),
        ];

        $response = $this->render('admin_staff/dashboard/index.html.twig', [
            'totalProjects' => $totalProjects,
            'totalCategories' => $totalCategories,
            'totalUsers' => $totalUsers,
            'totalAdmins' => $totalAdmins,
            'totalStaff' => $totalStaff,
            'completedProjects' => $completedProjects,
            'ongoingProjects' => $ongoingProjects,
            'pendingProjects' => $pendingProjects,
            'activeProjects' => $activeProjects,
            'cancelledProjects' => $cancelledProjects,
            'recentProjects' => $recentProjects,
            // User Activity Summary
            'usersByRole' => $usersByRole,
            'recentLogins' => $recentLogins,
            'activeUsers' => $activeUsers,
            'pendingVerifications' => $pendingVerifications,
            // Proposal Statistics
            'totalProposals' => $totalProposals,
            'pendingProposals' => $pendingProposals,
            'acceptedProposals' => $acceptedProposals,
            'rejectedProposals' => $rejectedProposals,
            'recentProposals' => $recentProposals,
            // File Statistics
            'totalFiles' => $totalFiles,
            'totalFileSize' => $totalFileSize,
            'totalFileSizeFormatted' => $totalFileSizeFormatted,
            'imageFiles' => $imageFiles,
            'documentFiles' => $documentFiles,
            'orphanedFiles' => $orphanedFiles,
            'filesInUse' => $filesInUse,
            'recentFiles' => $recentFiles,
            // Analytics
            'analytics' => $analytics,
        ]);
        
        // Prevent caching to avoid back button issues after logout
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Revenue Analytics
     */
    private function getRevenueAnalytics(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        \DateTimeImmutable $previousStartDate,
        \DateTimeImmutable $previousEndDate,
        ProjectRepository $projectRepository,
        ProposalRepository $proposalRepository
    ): array {
        $completedProjects = $projectRepository->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->setParameter('status', 'completed')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        $totalRevenue = array_sum(array_map(fn($p) => $p->getBudget() ?? 0, $completedProjects));

        $previousCompletedProjects = $projectRepository->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->setParameter('status', 'completed')
            ->setParameter('startDate', $previousStartDate)
            ->setParameter('endDate', $previousEndDate)
            ->getQuery()
            ->getResult();

        $previousRevenue = array_sum(array_map(fn($p) => $p->getBudget() ?? 0, $previousCompletedProjects));

        $revenueChange = $previousRevenue > 0 
            ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 
            : 0;

        return [
            'totalRevenue' => $totalRevenue,
            'previousRevenue' => $previousRevenue,
            'revenueChange' => round($revenueChange, 2),
        ];
    }

    /**
     * User Growth Analytics
     */
    private function getUserGrowthAnalytics(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        \DateTimeImmutable $previousStartDate,
        \DateTimeImmutable $previousEndDate,
        UsersRepository $usersRepository
    ): array {
        $newUsers = $usersRepository->createQueryBuilder('u')
            ->where('u.createdAt >= :startDate')
            ->andWhere('u.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        $previousNewUsers = $usersRepository->createQueryBuilder('u')
            ->where('u.createdAt >= :startDate')
            ->andWhere('u.createdAt <= :endDate')
            ->setParameter('startDate', $previousStartDate)
            ->setParameter('endDate', $previousEndDate)
            ->getQuery()
            ->getResult();

        $growthChange = count($previousNewUsers) > 0 
            ? ((count($newUsers) - count($previousNewUsers)) / count($previousNewUsers)) * 100 
            : 0;

        return [
            'newRegistrations' => count($newUsers),
            'previousRegistrations' => count($previousNewUsers),
            'growthChange' => round($growthChange, 2),
        ];
    }

    /**
     * Project Metrics
     */
    private function getProjectMetrics(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        \DateTimeImmutable $previousStartDate,
        \DateTimeImmutable $previousEndDate,
        ProjectRepository $projectRepository
    ): array {
        $projects = $projectRepository->createQueryBuilder('p')
            ->where('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        $completedProjects = array_filter($projects, fn($p) => $p->getStatus() === 'completed');
        $budgets = array_map(fn($p) => $p->getBudget() ?? 0, $projects);
        $averageBudget = count($budgets) > 0 ? array_sum($budgets) / count($budgets) : 0;

        return [
            'totalProjects' => count($projects),
            'completedProjects' => count($completedProjects),
            'completionRate' => count($projects) > 0 ? (count($completedProjects) / count($projects)) * 100 : 0,
            'averageBudget' => round($averageBudget, 2),
        ];
    }

    /**
     * Time Series Data for Charts
     */
    private function getTimeSeriesData(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        ProjectRepository $projectRepository,
        UsersRepository $usersRepository,
        ProposalRepository $proposalRepository
    ): array {
        $days = $startDate->diff($endDate)->days;
        $interval = $days <= 7 ? 'day' : ($days <= 30 ? 'week' : 'month');
        
        $labels = [];
        $revenueData = [];
        $userData = [];
        $projectData = [];

        $current = clone $startDate;
        while ($current <= $endDate) {
            // Calculate period end
            $periodEnd = null;
            if ($interval === 'day') {
                $periodEnd = (clone $current)->modify('+1 day');
            } elseif ($interval === 'week') {
                $periodEnd = (clone $current)->modify('+1 week');
            } else {
                $periodEnd = (clone $current)->modify('+1 month');
            }

            // Format label
            if ($interval === 'day') {
                $labels[] = $current->format('M d');
            } elseif ($interval === 'week') {
                $labels[] = 'Week of ' . $current->format('M d');
            } else {
                $labels[] = $current->format('M Y');
            }

            // Revenue for period
            $periodProjects = $projectRepository->createQueryBuilder('p')
                ->where('p.status = :status')
                ->andWhere('p.createdAt >= :start')
                ->andWhere('p.createdAt < :end')
                ->setParameter('status', 'completed')
                ->setParameter('start', $current)
                ->setParameter('end', $periodEnd)
                ->getQuery()
                ->getResult();
            $revenueData[] = array_sum(array_map(fn($p) => $p->getBudget() ?? 0, $periodProjects));

            // Users for period (exclude admin users)
            $periodUsers = $usersRepository->createQueryBuilder('u')
                ->where('u.createdAt >= :start')
                ->andWhere('u.createdAt < :end')
                ->setParameter('start', $current)
                ->setParameter('end', $periodEnd)
                ->getQuery()
                ->getResult();
            $userData[] = count($periodUsers);

            // Projects for period
            $periodProjectsAll = $projectRepository->createQueryBuilder('p')
                ->where('p.createdAt >= :start')
                ->andWhere('p.createdAt < :end')
                ->setParameter('start', $current)
                ->setParameter('end', $periodEnd)
                ->getQuery()
                ->getResult();
            $projectData[] = count($periodProjectsAll);

            $current = $periodEnd;
        }

        return [
            'labels' => $labels,
            'revenue' => $revenueData,
            'users' => $userData,
            'projects' => $projectData,
        ];
    }
}
