<?php

namespace App\Controller;

use App\Repository\UsersRepository;
use App\Repository\ProjectRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProposalRepository;
use App\Repository\FileRepository;
use App\Repository\ActivityLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[Route('/search')]
final class SearchController extends AbstractController
{
    #[Route('/users', name: 'app_admin_search_users', methods: ['GET'])]
    public function searchUsers(Request $request, UsersRepository $usersRepository): JsonResponse
    {
        // Restrict user search to ROLE_ADMIN only
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['results' => [], 'error' => 'Access denied'], 403);
        }
        
        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $users = $usersRepository->createQueryBuilder('u')
            ->where('u.username LIKE :query')
            ->orWhere('u.email LIKE :query')
            ->orWhere('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->orWhere('u.userType LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.username', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => $user->getId(),
                'type' => 'user',
                'title' => $user->getFirstName() . ' ' . $user->getLastName(),
                'subtitle' => '@' . $user->getUsername() . ' • ' . $user->getEmail(),
                'badge' => [
                    'text' => ucfirst($user->getUserType()),
                    'class' => $user->getUserType() === 'admin' ? 'badge-danger' : 
                              ($user->getUserType() === 'designer' ? 'badge-warning' : 'badge-info')
                ],
                'status' => [
                    'text' => $user->isActive() ? 'Active' : 'Disabled',
                    'class' => $user->isActive() ? 'is-active' : 'is-inactive'
                ],
                'url' => $this->generateUrl('app_user_show', ['id' => $user->getId()])
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    #[Route('/categories', name: 'app_admin_search_categories', methods: ['GET'])]
    public function searchCategories(Request $request, CategoryRepository $categoryRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $categories = $categoryRepository->createQueryBuilder('c')
            ->where('c.name LIKE :query')
            ->orWhere('c.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($categories as $category) {
            $results[] = [
                'id' => $category->getId(),
                'type' => 'category',
                'title' => $category->getName(),
                'subtitle' => $category->getDescription() ?: 'No description',
                'badge' => [
                    'text' => 'Category',
                    'class' => 'badge-secondary'
                ],
                'url' => $this->generateUrl('app_category_show', ['id' => $category->getId()])
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    #[Route('/projects', name: 'app_admin_search_projects', methods: ['GET'])]
    public function searchProjects(Request $request, ProjectRepository $projectRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $projects = $projectRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.client', 'client')
            ->addSelect('client')
            ->where('p.title LIKE :query')
            ->orWhere('p.description LIKE :query')
            ->orWhere('c.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.title', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($projects as $project) {
            $clientInfo = $project->getClient() ? '@' . $project->getClient()->getUsername() : 'No Client';
            $categoryInfo = $project->getCategory() ? $project->getCategory()->getName() : 'No Category';
            
            $results[] = [
                'id' => $project->getId(),
                'type' => 'project',
                'title' => $project->getTitle(),
                'subtitle' => $categoryInfo . ' • ' . $clientInfo,
                'badge' => [
                    'text' => ucfirst($project->getStatus()),
                    'class' => $project->getStatus() === 'completed' ? 'badge-success' : 
                              ($project->getStatus() === 'ongoing' ? 'badge-warning' : 
                              ($project->getStatus() === 'pending' ? 'badge-info' : 'badge-secondary'))
                ],
                'url' => $this->generateUrl('app_admin_project_show', ['id' => $project->getId()])
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    #[Route('/global', name: 'app_admin_search_global', methods: ['GET'])]
    public function searchGlobal(
        Request $request, 
        UsersRepository $usersRepository,
        ProjectRepository $projectRepository,
        CategoryRepository $categoryRepository,
        ProposalRepository $proposalRepository,
        FileRepository $fileRepository
    ): JsonResponse {
        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $results = [];

        // Search Users (only for ROLE_ADMIN)
        if ($this->isGranted('ROLE_ADMIN')) {
            $users = $usersRepository->createQueryBuilder('u')
                ->where('u.username LIKE :query')
                ->orWhere('u.email LIKE :query')
                ->orWhere('u.firstName LIKE :query')
                ->orWhere('u.lastName LIKE :query')
                ->setParameter('query', '%' . $query . '%')
                ->orderBy('u.username', 'ASC')
                ->setMaxResults(5)
                ->getQuery()
                ->getResult();

            foreach ($users as $user) {
                $results[] = [
                    'id' => $user->getId(),
                    'type' => 'user',
                    'title' => $user->getFirstName() . ' ' . $user->getLastName(),
                    'subtitle' => '@' . $user->getUsername() . ' • ' . $user->getEmail(),
                    'badge' => [
                        'text' => ucfirst($user->getUserType()),
                        'class' => $user->getUserType() === 'admin' ? 'badge-danger' : 
                                  ($user->getUserType() === 'designer' ? 'badge-warning' : 'badge-info')
                    ],
                    'url' => $this->generateUrl('app_user_show', ['id' => $user->getId()])
                ];
            }
        }

        // Search Projects
        $projects = $projectRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->where('p.title LIKE :query')
            ->orWhere('p.description LIKE :query')
            ->orWhere('c.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('p.title', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($projects as $project) {
            $results[] = [
                'id' => $project->getId(),
                'type' => 'project',
                'title' => $project->getTitle(),
                'subtitle' => $project->getCategory() ? $project->getCategory()->getName() : 'No Category',
                'badge' => [
                    'text' => ucfirst($project->getStatus()),
                    'class' => $project->getStatus() === 'completed' ? 'badge-success' : 
                              ($project->getStatus() === 'ongoing' ? 'badge-warning' : 'badge-info')
                ],
                'url' => $this->generateUrl('app_admin_project_show', ['id' => $project->getId()])
            ];
        }

        // Search Categories
        $categories = $categoryRepository->createQueryBuilder('c')
            ->where('c.name LIKE :query')
            ->orWhere('c.description LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($categories as $category) {
            $results[] = [
                'id' => $category->getId(),
                'type' => 'category',
                'title' => $category->getName(),
                'subtitle' => $category->getDescription() ?: 'No description',
                'badge' => [
                    'text' => 'Category',
                    'class' => 'badge-secondary'
                ],
                'url' => $this->generateUrl('app_category_show', ['id' => $category->getId()])
            ];
        }

        // Search Proposals
        $proposals = $proposalRepository->createQueryBuilder('pr')
            ->leftJoin('pr.project', 'p')
            ->addSelect('p')
            ->leftJoin('pr.designer', 'd')
            ->addSelect('d')
            ->where('pr.proposalText LIKE :query')
            ->orWhere('pr.coverLetter LIKE :query')
            ->orWhere('p.title LIKE :query')
            ->orWhere('d.username LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('pr.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($proposals as $proposal) {
            $projectTitle = $proposal->getProject() ? $proposal->getProject()->getTitle() : 'No Project';
            $designerName = $proposal->getDesigner() ? $proposal->getDesigner()->getUsername() : 'No Designer';
            
            $results[] = [
                'id' => $proposal->getId(),
                'type' => 'proposal',
                'title' => 'Proposal #' . $proposal->getId() . ' - ' . $projectTitle,
                'subtitle' => $designerName . ' • $' . number_format($proposal->getProposedPrice(), 2),
                'badge' => [
                    'text' => ucfirst($proposal->getStatus()),
                    'class' => $proposal->getStatus() === 'accepted' ? 'badge-success' : 
                              ($proposal->getStatus() === 'rejected' ? 'badge-danger' : 'badge-info')
                ],
                'url' => $this->generateUrl('app_admin_proposal_show', ['id' => $proposal->getId()])
            ];
        }

        // Search Files
        $files = $fileRepository->createQueryBuilder('f')
            ->where('f.filename LIKE :query')
            ->orWhere('f.path LIKE :query')
            ->andWhere('f.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('f.uploadedAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        foreach ($files as $file) {
            $type = $file->getType();
            $badgeClass = 'badge-secondary';
            if ($type === 'image') {
                $badgeClass = 'badge-success';
            } elseif ($type === 'document') {
                $badgeClass = 'badge-info';
            }
            
            $results[] = [
                'id' => $file->getId(),
                'type' => 'file',
                'title' => $file->getFilename(),
                'subtitle' => ucfirst($type) . ' • ' . $file->getSizeFormatted(),
                'badge' => [
                    'text' => ucfirst($type),
                    'class' => $badgeClass
                ],
                'url' => $this->generateUrl('app_admin_file_show', ['path' => urlencode($file->getPath())])
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    #[Route('/proposals', name: 'app_admin_search_proposals', methods: ['GET'])]
    public function searchProposals(Request $request, ProposalRepository $proposalRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $proposals = $proposalRepository->createQueryBuilder('pr')
            ->leftJoin('pr.project', 'p')
            ->addSelect('p')
            ->leftJoin('pr.designer', 'd')
            ->addSelect('d')
            ->where('pr.proposalText LIKE :query')
            ->orWhere('pr.coverLetter LIKE :query')
            ->orWhere('p.title LIKE :query')
            ->orWhere('d.username LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('pr.createdAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($proposals as $proposal) {
            $projectTitle = $proposal->getProject() ? $proposal->getProject()->getTitle() : 'No Project';
            $designerName = $proposal->getDesigner() ? $proposal->getDesigner()->getUsername() : 'No Designer';
            
            $results[] = [
                'id' => $proposal->getId(),
                'type' => 'proposal',
                'title' => 'Proposal #' . $proposal->getId() . ' - ' . $projectTitle,
                'subtitle' => $designerName . ' • $' . number_format($proposal->getProposedPrice(), 2),
                'badge' => [
                    'text' => ucfirst($proposal->getStatus()),
                    'class' => $proposal->getStatus() === 'accepted' ? 'badge-success' : 
                              ($proposal->getStatus() === 'rejected' ? 'badge-danger' : 'badge-info')
                ],
                'url' => $this->generateUrl('app_admin_proposal_show', ['id' => $proposal->getId()])
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    #[Route('/files', name: 'app_admin_search_files', methods: ['GET'])]
    public function searchFiles(Request $request, FileRepository $fileRepository): JsonResponse
    {
        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        // Use repository to search files
        $files = $fileRepository->createQueryBuilder('f')
            ->where('f.filename LIKE :query')
            ->orWhere('f.path LIKE :query')
            ->andWhere('f.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('f.uploadedAt', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($files as $file) {
            $type = $file->getType();
            $badgeClass = 'badge-secondary';
            if ($type === 'image') {
                $badgeClass = 'badge-success';
            } elseif ($type === 'document') {
                $badgeClass = 'badge-info';
            }
            
            $results[] = [
                'id' => $file->getId(),
                'type' => 'file',
                'title' => $file->getFilename(),
                'subtitle' => ucfirst($type) . ' • ' . $file->getSizeFormatted(),
                'badge' => [
                    'text' => ucfirst($type),
                    'class' => $badgeClass
                ],
                'url' => $this->generateUrl('app_admin_file_show', ['path' => urlencode($file->getPath())])
            ];
        }

        return new JsonResponse(['results' => $results]);
    }

    #[Route('/activity-logs', name: 'app_admin_search_activity_logs', methods: ['GET'])]
    public function searchActivityLogs(
        Request $request,
        ActivityLogRepository $activityLogRepository
    ): JsonResponse {
        // Restrict activity log search to ROLE_ADMIN only
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['results' => [], 'error' => 'Access denied'], 403);
        }

        $query = $request->query->get('q', '');
        
        if (empty($query) || strlen($query) < 2) {
            return new JsonResponse(['results' => []]);
        }

        $logs = $activityLogRepository->createQueryBuilder('al')
            ->leftJoin('al.user', 'u')
            ->addSelect('u')
            ->where('al.username LIKE :query')
            ->orWhere('al.action LIKE :query')
            ->orWhere('al.entityType LIKE :query')
            ->orWhere('al.role LIKE :query')
            ->orWhere('al.details LIKE :query')
            ->orWhere('u.firstName LIKE :query')
            ->orWhere('u.lastName LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('al.createdAt', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $results = [];
        foreach ($logs as $log) {
            $username = $log->getUser() 
                ? $log->getUser()->getFirstName() . ' ' . $log->getUser()->getLastName()
                : $log->getUsername();
            
            $results[] = [
                'id' => $log->getId(),
                'type' => 'activity_log',
                'title' => $log->getAction() . ' - ' . ($log->getEntityType() ?: 'System'),
                'subtitle' => $username . ' • ' . $log->getRole() . ' • ' . $log->getCreatedAt()->format('Y-m-d H:i:s'),
                'badge' => [
                    'text' => $log->getAction(),
                    'class' => $log->getAction() === 'DELETE' ? 'badge-danger' : 
                              ($log->getAction() === 'CREATE' ? 'badge-success' : 
                              ($log->getAction() === 'UPDATE' ? 'badge-warning' : 'badge-info'))
                ],
                'url' => $this->generateUrl('app_activity_log_index', ['q' => $query])
            ];
        }

        return new JsonResponse(['results' => $results]);
    }
}

