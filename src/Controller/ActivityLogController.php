<?php

namespace App\Controller;

use App\Repository\ActivityLogRepository;
use App\Repository\UsersRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/activity-logs')]
#[IsGranted('ROLE_ADMIN')]
class ActivityLogController extends AbstractController
{
    #[Route('/', name: 'app_activity_log_index', methods: ['GET'])]
    public function index(
        ActivityLogRepository $activityLogRepository,
        UsersRepository $usersRepository,
        Request $request
    ): Response {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;
        $action = $request->query->get('action');
        $entityType = $request->query->get('entity_type');
        $username = $request->query->get('username');
        $dateFrom = $request->query->get('date_from');
        $dateTo = $request->query->get('date_to');

        $logs = $activityLogRepository->findPaginated($page, $limit, $action, $entityType, $username, $dateFrom, $dateTo);
        $totalLogs = $activityLogRepository->countLogs($action, $entityType, $username, $dateFrom, $dateTo);
        $totalPages = ceil($totalLogs / $limit);

        // Get unique actions and entity types for filters
        $allLogs = $activityLogRepository->findAll();
        $uniqueActions = array_values(array_unique(array_filter(array_map(fn($log) => $log->getAction(), $allLogs))));
        $uniqueEntityTypes = array_values(array_unique(array_filter(array_map(fn($log) => $log->getEntityType(), $allLogs))));
        $uniqueUsernames = array_values(array_unique(array_filter(array_map(fn($log) => $log->getUsername(), $allLogs))));
        
        // Provide default options if no logs exist yet
        $defaultActions = ['LOGIN', 'LOGOUT', 'CREATE', 'UPDATE', 'DELETE'];
        $defaultEntityTypes = ['User', 'Project', 'Proposal', 'Category', 'File'];
        
        // Use defaults if no logs exist, otherwise use unique values from database
        $availableActions = !empty($uniqueActions) ? $uniqueActions : $defaultActions;
        $availableEntityTypes = !empty($uniqueEntityTypes) ? $uniqueEntityTypes : $defaultEntityTypes;
        $availableUsernames = $uniqueUsernames;
        
        // Sort arrays for consistent display
        sort($availableActions);
        sort($availableEntityTypes);
        sort($availableUsernames);

        // Calculate statistics
        $stats = [
            'total' => $activityLogRepository->countLogs(),
            'today' => $activityLogRepository->countToday(),
            'thisWeek' => $activityLogRepository->countThisWeek(),
            'logins' => $activityLogRepository->countByAction('LOGIN'),
            'creates' => $activityLogRepository->countByAction('CREATE'),
            'updates' => $activityLogRepository->countByAction('UPDATE'),
            'deletes' => $activityLogRepository->countByAction('DELETE'),
        ];

        return $this->render('admin_staff/activitylogs/index.html.twig', [
            'logs' => $logs,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalLogs' => $totalLogs,
            'currentAction' => $action,
            'currentEntityType' => $entityType,
            'currentUsername' => $username,
            'currentDateFrom' => $dateFrom,
            'currentDateTo' => $dateTo,
            'availableActions' => $availableActions,
            'availableEntityTypes' => $availableEntityTypes,
            'availableUsernames' => $availableUsernames,
            'stats' => $stats,
        ]);
    }
}
