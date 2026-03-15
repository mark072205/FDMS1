<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Users;
use App\Form\ProjectType;
use App\Repository\CategoryRepository;
use App\Repository\ProjectRepository;
use App\Service\NotificationService;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ClientProjectsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityLogService $activityLogService
    ) {}

    #[Route('/client/projects', name: 'app_client_projects')]
    public function index(
        CategoryRepository $categoryRepository,
        ProjectRepository $projectRepository,
        Request $request
    ): Response {
        // Ensure user is authenticated and has ROLE_CLIENT
        $user = $this->getUser();
        if (!$user || !$this->isGranted('ROLE_CLIENT') || !$user instanceof Users) {
            return $this->redirectToRoute('app_login');
        }

        // Get filter parameters
        $categoryFilter = $request->query->get('category', '');
        $statusFilter = $request->query->get('status', '');
        $searchQuery = $request->query->get('search', '');
        $tab = $request->query->get('tab', 'my-projects');

        // Get categories for the template and filters
        $categories = $categoryRepository->findAll();

        // Build query for My Projects (projects created by logged-in client)
        $myProjectsQuery = $projectRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.client', 'client')
            ->addSelect('client')
            ->where('p.client = :client')
            ->setParameter('client', $user)
            ->orderBy('p.createdAt', 'DESC');

        // Apply filters for My Projects
        if ($categoryFilter && $tab === 'my-projects') {
            $myProjectsQuery->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryFilter);
        }
        if ($statusFilter && $tab === 'my-projects') {
            $myProjectsQuery->andWhere('p.status = :status')
                ->setParameter('status', $statusFilter);
        }
        if ($searchQuery && $tab === 'my-projects') {
            $myProjectsQuery->andWhere('p.title LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $searchQuery . '%');
        }

        $myProjects = $myProjectsQuery->getQuery()->getResult();

        // Build query for Explore Projects (all public projects from all clients)
        $exploreProjectsQuery = $projectRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.client', 'client')
            ->addSelect('client')
            ->where('p.client != :currentClient OR p.client IS NULL')
            ->setParameter('currentClient', $user)
            ->orderBy('p.createdAt', 'DESC');

        // Apply filters for Explore Projects
        if ($categoryFilter && $tab === 'explore') {
            $exploreProjectsQuery->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryFilter);
        }
        if ($statusFilter && $tab === 'explore') {
            $exploreProjectsQuery->andWhere('p.status = :status')
                ->setParameter('status', $statusFilter);
        }
        if ($searchQuery && $tab === 'explore') {
            $exploreProjectsQuery->andWhere('p.title LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $searchQuery . '%');
        }

        $exploreProjects = $exploreProjectsQuery->getQuery()->getResult();

        $response = $this->render('client/client_projects/index.html.twig', [
            'controller_name' => 'ClientProjectsController',
            'categories' => $categories,
            'myProjects' => $myProjects,
            'exploreProjects' => $exploreProjects,
            'currentTab' => $tab,
            'categoryFilter' => $categoryFilter,
            'statusFilter' => $statusFilter,
            'searchQuery' => $searchQuery,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/client/projects/new', name: 'app_client_projects_new', methods: ['GET'])]
    public function new(CategoryRepository $categoryRepository): Response
    {
        // Ensure user is authenticated and has ROLE_CLIENT
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isGranted('ROLE_CLIENT')) {
            return $this->redirectToRoute('app_client_homepage');
        }

        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
        
        // Get categories for the template
        $categories = $categoryRepository->findAll();

        $response = $this->render('client/client_projects/new.html.twig', [
            'project' => $project,
            'form' => $form,
            'categories' => $categories,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/client/projects/create', name: 'app_client_projects_create', methods: ['POST'])]
    public function create(
        Request $request,
        ValidatorInterface $validator,
        CategoryRepository $categoryRepository,
        NotificationService $notificationService
    ): JsonResponse {
        // Ensure user is authenticated
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You must be logged in to create a project.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Ensure user has ROLE_CLIENT
        if (!$this->isGranted('ROLE_CLIENT')) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Only clients can create projects.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Ensure user is an instance of Users entity
        if (!$user instanceof Users) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid user type.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get JSON data from request
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid request data.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create new project entity
        $project = new Project();
        
        // Set basic fields from request data
        $project->setTitle($data['title'] ?? '');
        $project->setDescription($data['description'] ?? '');
        
        // Handle budget - convert range to number if needed, or use direct value
        $budget = $data['budget'] ?? null;
        if ($budget) {
            // If budget is a range string (e.g., "500-1000"), convert to average
            if (is_string($budget) && strpos($budget, '-') !== false) {
                $rangeParts = explode('-', $budget);
                $min = (float) str_replace(['$', ','], '', $rangeParts[0]);
                $max = (float) str_replace(['$', ','], '', $rangeParts[1] ?? $rangeParts[0]);
                $budget = ($min + $max) / 2;
            } elseif (is_string($budget)) {
                // Remove currency symbols and commas
                $budget = (float) str_replace(['$', ','], '', $budget);
            }
            $project->setBudget((float) $budget);
        } else {
            $project->setBudget(0.0);
        }

        // Handle category
        $categoryId = $data['category'] ?? null;
        if ($categoryId) {
            // If category is provided as ID
            if (is_numeric($categoryId)) {
                $category = $categoryRepository->find($categoryId);
                if ($category) {
                    $project->setCategory($category);
                }
            } else {
                // If category is provided as name, find by name
                $category = $categoryRepository->findOneBy(['name' => $categoryId]);
                if ($category) {
                    $project->setCategory($category);
                }
            }
        }

        // Auto-set fields
        $project->setClient($user);
        $project->setCreatedAt(new \DateTimeImmutable());
        $project->setStatus('pending'); // Default status

        // Validate the project entity
        $errors = $validator->validate($project);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $propertyPath = $error->getPropertyPath();
                $errorMessages[$propertyPath] = $error->getMessage();
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Persist project to database
        try {
            $this->entityManager->persist($project);
            $this->entityManager->flush();

            // Manually log the creation (fallback if event subscriber doesn't fire)
            try {
                $this->activityLogService->log(
                    'CREATE',
                    'Project',
                    $project->getId(),
                    "Created Project: " . ($project->getTitle() ?? 'Unknown')
                );
            } catch (\Exception $e) {
                error_log('Failed to log project creation: ' . $e->getMessage());
            }

            // Notify admins about new project
            try {
                $notificationService->notifyNewProject($project);
            } catch (\Exception $e) {
                // Log error but don't fail project creation
                error_log('Failed to send notification for new project: ' . $e->getMessage());
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Project created successfully!',
                'project' => [
                    'id' => $project->getId(),
                    'title' => $project->getTitle(),
                    'status' => $project->getStatus(),
                    'createdAt' => $project->getCreatedAt()->format('Y-m-d H:i:s')
                ]
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred while creating the project: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/client/projects/{id}', name: 'app_client_project_show', methods: ['GET'], requirements: ['id' => '\d+'], priority: 1)]
    public function show(
        int $id,
        ProjectRepository $projectRepository,
        Request $request
    ): Response {
        /** @var Users $client */
        $client = $this->getUser();
        
        $isAjax = $request->isXmlHttpRequest() || $request->query->get('modal') === '1';
        
        if (!$client) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You must be logged in to view this project.'
                ], Response::HTTP_UNAUTHORIZED);
            }
            return $this->redirectToRoute('app_login');
        }

        // Find the project
        $project = $projectRepository->find($id);
        
        if (!$project) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Project not found.'
                ], Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Project not found.');
        }

        // Verify project belongs to logged-in client
        if ($project->getClient() !== $client) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You do not have access to this project.'
                ], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('You do not have access to this project.');
        }

        // Check if this is an AJAX request (for modal)
        if ($isAjax) {
            return $this->render('client/client_projects/view_modal.html.twig', [
                'project' => $project,
            ]);
        }

        // Regular page request (fallback)
        return $this->render('client/client_projects/show.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/client/projects/{id}/edit', name: 'app_client_project_edit', methods: ['GET'], requirements: ['id' => '\d+'], priority: 1)]
    public function edit(
        int $id,
        ProjectRepository $projectRepository,
        Request $request,
        CategoryRepository $categoryRepository
    ): Response {
        /** @var Users $client */
        $client = $this->getUser();
        
        $isAjax = $request->isXmlHttpRequest() || $request->query->get('modal') === '1';
        
        if (!$client) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You must be logged in to edit this project.'
                ], Response::HTTP_UNAUTHORIZED);
            }
            return $this->redirectToRoute('app_login');
        }

        // Find the project
        $project = $projectRepository->find($id);
        
        if (!$project) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Project not found.'
                ], Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Project not found.');
        }

        // Verify project belongs to logged-in client
        if ($project->getClient() !== $client) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You do not have access to this project.'
                ], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('You do not have access to this project.');
        }

        // Only allow editing if status is pending
        if ($project->getStatus() !== 'pending') {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You can only edit projects with pending status.'
                ], Response::HTTP_BAD_REQUEST);
            }
            $this->addFlash('error', 'You can only edit projects with pending status.');
            return $this->redirectToRoute('app_client_projects');
        }

        // Get categories for the template
        $categories = $categoryRepository->findAll();

        // Check if this is an AJAX request (for modal)
        if ($isAjax) {
            return $this->render('client/client_projects/edit_modal.html.twig', [
                'project' => $project,
                'categories' => $categories,
            ]);
        }

        // Regular page request (fallback)
        return $this->render('client/client_projects/edit.html.twig', [
            'project' => $project,
            'categories' => $categories,
        ]);
    }

    #[Route('/client/projects/{id}/update', name: 'app_client_project_update', methods: ['POST'])]
    public function update(
        Project $project,
        Request $request,
        ValidatorInterface $validator,
        CategoryRepository $categoryRepository,
        NotificationService $notificationService
    ): JsonResponse {
        /** @var Users $client */
        $client = $this->getUser();
        
        if (!$client) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You must be logged in to update a project.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verify project belongs to logged-in client
        if ($project->getClient() !== $client) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You do not have access to this project.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Only allow editing if status is pending
        if ($project->getStatus() !== 'pending') {
            return new JsonResponse([
                'success' => false,
                'message' => 'You can only edit projects with pending status.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get JSON data from request
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid request data.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update project fields
        if (isset($data['title'])) {
            $project->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $project->setDescription($data['description']);
        }
        
        // Handle budget
        if (isset($data['budget'])) {
            $budget = $data['budget'];
            if (is_string($budget) && strpos($budget, '-') !== false) {
                $rangeParts = explode('-', $budget);
                $min = (float) str_replace(['$', ','], '', $rangeParts[0]);
                $max = (float) str_replace(['$', ','], '', $rangeParts[1] ?? $rangeParts[0]);
                $budget = ($min + $max) / 2;
            } elseif (is_string($budget)) {
                $budget = (float) str_replace(['$', ','], '', $budget);
            }
            $project->setBudget((float) $budget);
        }

        // Handle category
        if (isset($data['category'])) {
            $categoryId = $data['category'];
            if (is_numeric($categoryId)) {
                $category = $categoryRepository->find($categoryId);
                if ($category) {
                    $project->setCategory($category);
                }
            } else {
                $category = $categoryRepository->findOneBy(['name' => $categoryId]);
                if ($category) {
                    $project->setCategory($category);
                }
            }
        }

        // Validate the project entity
        $errors = $validator->validate($project);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $propertyPath = $error->getPropertyPath();
                $errorMessages[$propertyPath] = $error->getMessage();
            }

            return new JsonResponse([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Save changes
        try {
            $this->entityManager->flush();

            // Manually log the update (fallback if event subscriber doesn't fire)
            try {
                $this->activityLogService->log(
                    'UPDATE',
                    'Project',
                    $project->getId(),
                    "Updated Project: " . ($project->getTitle() ?? 'Unknown')
                );
            } catch (\Exception $e) {
                error_log('Failed to log project update: ' . $e->getMessage());
            }

            // Notify admins about project update
            try {
                $notificationService->notifyProjectUpdate($project);
            } catch (\Exception $e) {
                // Log error but don't fail project update
                error_log('Failed to send notification for project update: ' . $e->getMessage());
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Project updated successfully!',
                'project' => [
                    'id' => $project->getId(),
                    'title' => $project->getTitle(),
                    'status' => $project->getStatus(),
                ]
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred while updating the project: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/client/projects/{id}/delete', name: 'app_client_project_delete', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function delete(
        int $id,
        ProjectRepository $projectRepository,
        Request $request,
        NotificationService $notificationService
    ) {
        /** @var Users $client */
        $client = $this->getUser();
        
        $isAjax = $request->isXmlHttpRequest() || $request->query->get('modal') === '1';
        
        if (!$client) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You must be logged in to delete a project.'
                ], Response::HTTP_UNAUTHORIZED);
            }
            return $this->redirectToRoute('app_login');
        }

        // Find the project
        $project = $projectRepository->find($id);
        
        if (!$project) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Project not found.'
                ], Response::HTTP_NOT_FOUND);
            }
            throw $this->createNotFoundException('Project not found.');
        }

        // Verify project belongs to logged-in client
        if ($project->getClient() !== $client) {
            if ($isAjax) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'You do not have access to this project.'
                ], Response::HTTP_FORBIDDEN);
            }
            throw $this->createAccessDeniedException('You do not have access to this project.');
        }

        // Handle GET request - show delete confirmation modal
        if ($request->isMethod('GET')) {
            if ($isAjax) {
                return $this->render('client/client_projects/delete_modal.html.twig', [
                    'project' => $project,
                ]);
            }
            // For non-AJAX GET requests, redirect to projects page
            return $this->redirectToRoute('app_client_projects');
        }

        // Handle POST request - actually delete the project
        if ($request->isMethod('POST')) {
            // Note: CSRF protection can be added here if needed
            // For now, we rely on authentication and ownership verification

            try {
                $projectId = $project->getId();
                $projectTitle = $project->getTitle() ?? 'Unknown';

                // Log deletion before removing (preRemove event will also fire, but this ensures it's logged)
                try {
                    $this->activityLogService->log(
                        'DELETE',
                        'Project',
                        $projectId,
                        "Deleted Project: " . $projectTitle
                    );
                } catch (\Exception $e) {
                    error_log('Failed to log project deletion: ' . $e->getMessage());
                }

                // Notify admins about project deletion before deletion
                try {
                    $notificationService->notifyProjectDelete($project);
                } catch (\Exception $e) {
                    // Log error but don't fail project deletion
                    error_log('Failed to send notification for project deletion: ' . $e->getMessage());
                }
                
                // Delete all proposals associated with this project first
                $proposals = $project->getProposals();
                foreach ($proposals as $proposal) {
                    $this->entityManager->remove($proposal);
                }
                
                // Delete the project
                $this->entityManager->remove($project);
                $this->entityManager->flush();

                return new JsonResponse([
                    'success' => true,
                    'message' => 'Project deleted successfully.'
                ]);
            } catch (\Exception $e) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'An error occurred while deleting the project: ' . $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }

        // Fallback for non-AJAX requests
        return new JsonResponse([
            'success' => false,
            'message' => 'Invalid request method.'
        ], Response::HTTP_METHOD_NOT_ALLOWED);
    }
}

