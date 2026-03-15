<?php

namespace App\Controller;

use App\Repository\ProjectRepository;
use App\Repository\ProposalRepository;
use App\Repository\UsersRepository;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/analytics')]
final class AnalyticsController extends AbstractController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private ProposalRepository $proposalRepository,
        private UsersRepository $usersRepository,
        private CategoryRepository $categoryRepository
    ) {}

    #[Route('/', name: 'app_admin_analytics_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Get date range from request (default to last 30 days)
        $startDate = $request->query->get('start_date') 
            ? new \DateTimeImmutable($request->query->get('start_date'))
            : new \DateTimeImmutable('-30 days');
        
        $endDate = $request->query->get('end_date')
            ? new \DateTimeImmutable($request->query->get('end_date'))
            : new \DateTimeImmutable('now');

        // Calculate previous period for comparison
        $periodDays = $startDate->diff($endDate)->days;
        $previousStartDate = $startDate->modify("-{$periodDays} days");
        $previousEndDate = $startDate;

        // Gather all analytics data
        $analytics = [
            'revenue' => $this->getRevenueAnalytics($startDate, $endDate, $previousStartDate, $previousEndDate),
            'userGrowth' => $this->getUserGrowthAnalytics($startDate, $endDate, $previousStartDate, $previousEndDate),
            'projectMetrics' => $this->getProjectMetrics($startDate, $endDate, $previousStartDate, $previousEndDate),
            'designerPerformance' => $this->getDesignerPerformance($startDate, $endDate),
            'clientBehavior' => $this->getClientBehavior($startDate, $endDate),
            'categoryPerformance' => $this->getCategoryPerformance($startDate, $endDate),
            'timeSeries' => $this->getTimeSeriesData($startDate, $endDate),
        ];

        return $this->render('admin_staff/analytics/index.html.twig', [
            'analytics' => $analytics,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'previousStartDate' => $previousStartDate,
            'previousEndDate' => $previousEndDate,
        ]);
    }

    #[Route('/api/data', name: 'app_admin_analytics_api', methods: ['GET'])]
    public function getAnalyticsData(Request $request): JsonResponse
    {
        $startDate = $request->query->get('start_date') 
            ? new \DateTimeImmutable($request->query->get('start_date'))
            : new \DateTimeImmutable('-30 days');
        
        $endDate = $request->query->get('end_date')
            ? new \DateTimeImmutable($request->query->get('end_date'))
            : new \DateTimeImmutable('now');

        $periodDays = $startDate->diff($endDate)->days;
        $previousStartDate = $startDate->modify("-{$periodDays} days");
        $previousEndDate = $startDate;

        $analytics = [
            'revenue' => $this->getRevenueAnalytics($startDate, $endDate, $previousStartDate, $previousEndDate),
            'userGrowth' => $this->getUserGrowthAnalytics($startDate, $endDate, $previousStartDate, $previousEndDate),
            'projectMetrics' => $this->getProjectMetrics($startDate, $endDate, $previousStartDate, $previousEndDate),
            'designerPerformance' => $this->getDesignerPerformance($startDate, $endDate),
            'clientBehavior' => $this->getClientBehavior($startDate, $endDate),
            'categoryPerformance' => $this->getCategoryPerformance($startDate, $endDate),
            'timeSeries' => $this->getTimeSeriesData($startDate, $endDate),
        ];

        return new JsonResponse($analytics);
    }

    /**
     * Revenue Analytics
     */
    private function getRevenueAnalytics(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        \DateTimeImmutable $previousStartDate,
        \DateTimeImmutable $previousEndDate
    ): array {
        // Total revenue from completed projects (using project budget)
        $completedProjects = $this->projectRepository->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->setParameter('status', 'completed')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        $totalRevenue = array_sum(array_map(fn($p) => $p->getBudget() ?? 0, $completedProjects));

        // Previous period revenue
        $previousCompletedProjects = $this->projectRepository->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->setParameter('status', 'completed')
            ->setParameter('startDate', $previousStartDate)
            ->setParameter('endDate', $previousEndDate)
            ->getQuery()
            ->getResult();

        $previousRevenue = array_sum(array_map(fn($p) => $p->getBudget() ?? 0, $previousCompletedProjects));

        // Revenue from accepted proposals (alternative calculation)
        $acceptedProposals = $this->proposalRepository->createQueryBuilder('pr')
            ->where('pr.status = :status')
            ->andWhere('pr.createdAt >= :startDate')
            ->andWhere('pr.createdAt <= :endDate')
            ->setParameter('status', 'accepted')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        $proposalRevenue = array_sum(array_map(fn($pr) => $pr->getProposedPrice() ?? 0, $acceptedProposals));

        // Use the higher of the two (or combine if needed)
        $revenueChange = $previousRevenue > 0 
            ? (($totalRevenue - $previousRevenue) / $previousRevenue) * 100 
            : 0;

        return [
            'totalRevenue' => $totalRevenue,
            'proposalRevenue' => $proposalRevenue,
            'previousRevenue' => $previousRevenue,
            'revenueChange' => round($revenueChange, 2),
            'completedProjectsCount' => count($completedProjects),
            'acceptedProposalsCount' => count($acceptedProposals),
        ];
    }

    /**
     * User Growth Analytics
     */
    private function getUserGrowthAnalytics(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        \DateTimeImmutable $previousStartDate,
        \DateTimeImmutable $previousEndDate
    ): array {
        // New registrations in period
        $newUsers = $this->usersRepository->createQueryBuilder('u')
            ->where('u.createdAt >= :startDate')
            ->andWhere('u.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        // Previous period registrations
        $previousNewUsers = $this->usersRepository->createQueryBuilder('u')
            ->where('u.createdAt >= :startDate')
            ->andWhere('u.createdAt <= :endDate')
            ->setParameter('startDate', $previousStartDate)
            ->setParameter('endDate', $previousEndDate)
            ->getQuery()
            ->getResult();

        // Active users (logged in within last 7 days)
        $activeUsers = $this->usersRepository->createQueryBuilder('u')
            ->where('u.lastActivity >= :activeDate')
            ->andWhere('u.isActive = :active')
            ->setParameter('activeDate', new \DateTime('-7 days'))
            ->setParameter('active', true)
            ->getQuery()
            ->getResult();

        // Total users
        $totalUsers = $this->usersRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        // User growth by role
        $newAdmins = array_filter($newUsers, fn($u) => $u->getUserType() === 'admin');
        $newStaff = array_filter($newUsers, fn($u) => $u->getUserType() === 'staff');
        $newClients = array_filter($newUsers, fn($u) => $u->getUserType() === 'client');
        $newDesigners = array_filter($newUsers, fn($u) => $u->getUserType() === 'designer');

        $growthChange = count($previousNewUsers) > 0 
            ? ((count($newUsers) - count($previousNewUsers)) / count($previousNewUsers)) * 100 
            : 0;

        return [
            'newRegistrations' => count($newUsers),
            'previousRegistrations' => count($previousNewUsers),
            'growthChange' => round($growthChange, 2),
            'activeUsers' => count($activeUsers),
            'totalUsers' => (int)$totalUsers,
            'newAdmins' => count($newAdmins),
            'newStaff' => count($newStaff),
            'newClients' => count($newClients),
            'newDesigners' => count($newDesigners),
        ];
    }

    /**
     * Project Metrics
     */
    private function getProjectMetrics(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate,
        \DateTimeImmutable $previousStartDate,
        \DateTimeImmutable $previousEndDate
    ): array {
        // All projects in period
        $projects = $this->projectRepository->createQueryBuilder('p')
            ->where('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->getQuery()
            ->getResult();

        $completedProjects = array_filter($projects, fn($p) => $p->getStatus() === 'completed');
        $pendingProjects = array_filter($projects, fn($p) => $p->getStatus() === 'pending');
        $ongoingProjects = array_filter($projects, fn($p) => $p->getStatus() === 'ongoing');

        // Average budget
        $budgets = array_map(fn($p) => $p->getBudget() ?? 0, $projects);
        $averageBudget = count($budgets) > 0 ? array_sum($budgets) / count($budgets) : 0;

        // Completion rate
        $completionRate = count($projects) > 0 
            ? (count($completedProjects) / count($projects)) * 100 
            : 0;

        // Previous period for comparison
        $previousProjects = $this->projectRepository->createQueryBuilder('p')
            ->where('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->setParameter('startDate', $previousStartDate)
            ->setParameter('endDate', $previousEndDate)
            ->getQuery()
            ->getResult();

        $previousCompleted = array_filter($previousProjects, fn($p) => $p->getStatus() === 'completed');
        $previousCompletionRate = count($previousProjects) > 0 
            ? (count($previousCompleted) / count($previousProjects)) * 100 
            : 0;

        $completionRateChange = $previousCompletionRate > 0 
            ? (($completionRate - $previousCompletionRate) / $previousCompletionRate) * 100 
            : 0;

        return [
            'totalProjects' => count($projects),
            'completedProjects' => count($completedProjects),
            'pendingProjects' => count($pendingProjects),
            'ongoingProjects' => count($ongoingProjects),
            'averageBudget' => round($averageBudget, 2),
            'completionRate' => round($completionRate, 2),
            'previousCompletionRate' => round($previousCompletionRate, 2),
            'completionRateChange' => round($completionRateChange, 2),
        ];
    }

    /**
     * Designer Performance
     */
    private function getDesignerPerformance(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        // Get all designers
        $designers = $this->usersRepository->findByRole('ROLE_DESIGNER');

        $designerStats = [];
        foreach ($designers as $designer) {
            $proposals = $this->proposalRepository->createQueryBuilder('pr')
                ->where('pr.designer = :designer')
                ->andWhere('pr.createdAt >= :startDate')
                ->andWhere('pr.createdAt <= :endDate')
                ->setParameter('designer', $designer)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->getQuery()
                ->getResult();

            $acceptedProposals = array_filter($proposals, fn($pr) => $pr->getStatus() === 'accepted');
            $totalProposals = count($proposals);
            $acceptanceRate = $totalProposals > 0 
                ? (count($acceptedProposals) / $totalProposals) * 100 
                : 0;

            $totalRevenue = array_sum(array_map(fn($pr) => $pr->getProposedPrice() ?? 0, $acceptedProposals));

            if ($totalProposals > 0) {
                $designerStats[] = [
                    'id' => $designer->getId(),
                    'name' => $designer->getFirstName() . ' ' . $designer->getLastName(),
                    'username' => $designer->getUsername(),
                    'totalProposals' => $totalProposals,
                    'acceptedProposals' => count($acceptedProposals),
                    'acceptanceRate' => round($acceptanceRate, 2),
                    'totalRevenue' => round($totalRevenue, 2),
                ];
            }
        }

        // Sort by total revenue descending
        usort($designerStats, fn($a, $b) => $b['totalRevenue'] <=> $a['totalRevenue']);

        return [
            'topPerformers' => array_slice($designerStats, 0, 10),
            'totalDesigners' => count($designers),
            'activeDesigners' => count(array_filter($designerStats, fn($d) => $d['totalProposals'] > 0)),
        ];
    }

    /**
     * Client Behavior
     */
    private function getClientBehavior(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        // Get all clients
        $clients = $this->usersRepository->findByRole('ROLE_CLIENT');

        $clientStats = [];
        foreach ($clients as $client) {
            $projects = $this->projectRepository->createQueryBuilder('p')
                ->where('p.client = :client')
                ->andWhere('p.createdAt >= :startDate')
                ->andWhere('p.createdAt <= :endDate')
                ->setParameter('client', $client)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->getQuery()
                ->getResult();

            $totalSpent = array_sum(array_map(fn($p) => $p->getBudget() ?? 0, $projects));
            $projectCount = count($projects);

            if ($projectCount > 0) {
                $clientStats[] = [
                    'id' => $client->getId(),
                    'name' => $client->getFirstName() . ' ' . $client->getLastName(),
                    'username' => $client->getUsername(),
                    'projectCount' => $projectCount,
                    'totalSpent' => round($totalSpent, 2),
                    'averageSpent' => round($totalSpent / $projectCount, 2),
                ];
            }
        }

        // Sort by total spent descending
        usort($clientStats, fn($a, $b) => $b['totalSpent'] <=> $a['totalSpent']);

        // Calculate averages
        $totalSpending = array_sum(array_column($clientStats, 'totalSpent'));
        $averageSpending = count($clientStats) > 0 ? $totalSpending / count($clientStats) : 0;
        $averageProjectsPerClient = count($clients) > 0 
            ? array_sum(array_column($clientStats, 'projectCount')) / count($clients) 
            : 0;

        return [
            'topSpenders' => array_slice($clientStats, 0, 10),
            'totalClients' => count($clients),
            'activeClients' => count($clientStats),
            'averageSpending' => round($averageSpending, 2),
            'averageProjectsPerClient' => round($averageProjectsPerClient, 2),
        ];
    }

    /**
     * Category Performance
     */
    private function getCategoryPerformance(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $categories = $this->categoryRepository->findAll();

        $categoryStats = [];
        foreach ($categories as $category) {
            $projects = $this->projectRepository->createQueryBuilder('p')
                ->where('p.category = :category')
                ->andWhere('p.createdAt >= :startDate')
                ->andWhere('p.createdAt <= :endDate')
                ->setParameter('category', $category)
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->getQuery()
                ->getResult();

            $completedProjects = array_filter($projects, fn($p) => $p->getStatus() === 'completed');
            $totalRevenue = array_sum(array_map(fn($p) => $p->getBudget() ?? 0, $completedProjects));

            $categoryStats[] = [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'projectCount' => count($projects),
                'completedCount' => count($completedProjects),
                'totalRevenue' => round($totalRevenue, 2),
            ];
        }

        // Sort by project count descending
        usort($categoryStats, fn($a, $b) => $b['projectCount'] <=> $a['projectCount']);

        return $categoryStats;
    }

    /**
     * Time Series Data for Charts
     */
    private function getTimeSeriesData(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): array
    {
        $days = $startDate->diff($endDate)->days;
        $interval = $days <= 30 ? 'day' : ($days <= 90 ? 'week' : 'month');

        $labels = [];
        $revenueData = [];
        $userData = [];
        $projectData = [];
        $proposalData = [];

        $current = clone $startDate;
        while ($current <= $endDate) {
            // Calculate period end
            $periodEnd = null;
            if ($interval === 'day') {
                $periodEnd = $current->modify('+1 day');
            } elseif ($interval === 'week') {
                $periodEnd = $current->modify('+1 week');
            } else {
                $periodEnd = $current->modify('+1 month');
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
            $periodCompletedProjects = $this->projectRepository->createQueryBuilder('p')
                ->where('p.status = :status')
                ->andWhere('p.createdAt >= :start')
                ->andWhere('p.createdAt < :end')
                ->setParameter('status', 'completed')
                ->setParameter('start', $current)
                ->setParameter('end', $periodEnd)
                ->getQuery()
                ->getResult();

            $revenueData[] = array_sum(array_map(fn($p) => $p->getBudget() ?? 0, $periodCompletedProjects));

            // New users for period
            $periodUsers = $this->usersRepository->createQueryBuilder('u')
                ->where('u.createdAt >= :start')
                ->andWhere('u.createdAt < :end')
                ->setParameter('start', $current)
                ->setParameter('end', $periodEnd)
                ->getQuery()
                ->getResult();

            $userData[] = count($periodUsers);

            // New projects for period
            $periodProjects = $this->projectRepository->createQueryBuilder('p')
                ->where('p.createdAt >= :start')
                ->andWhere('p.createdAt < :end')
                ->setParameter('start', $current)
                ->setParameter('end', $periodEnd)
                ->getQuery()
                ->getResult();

            $projectData[] = count($periodProjects);

            // New proposals for period
            $periodProposals = $this->proposalRepository->createQueryBuilder('pr')
                ->where('pr.createdAt >= :start')
                ->andWhere('pr.createdAt < :end')
                ->setParameter('start', $current)
                ->setParameter('end', $periodEnd)
                ->getQuery()
                ->getResult();

            $proposalData[] = count($periodProposals);

            $current = $periodEnd;
        }

        return [
            'labels' => $labels,
            'revenue' => $revenueData,
            'users' => $userData,
            'projects' => $projectData,
            'proposals' => $proposalData,
            'interval' => $interval,
        ];
    }
}

