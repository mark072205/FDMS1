<?php

namespace App\Controller;

use App\Entity\Project;
use App\Repository\ProjectRepository;
use App\Repository\CategoryRepository;
use App\Repository\UsersRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/projects')]
final class ProjectsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityLogService $activityLogService
    ) {}

    #[Route('/', name: 'app_admin_project_index', methods: ['GET'])]
    public function index(
        ProjectRepository $projectRepository,
        CategoryRepository $categoryRepository,
        UsersRepository $usersRepository,
        Request $request
    ): Response {
        // Get filter parameters
        $statusFilter = $request->query->get('status', '');
        $categoryFilter = $request->query->get('category', '');
        $clientFilter = $request->query->get('client', '');
        $searchQuery = $request->query->get('search', '');

        // Build query
        $qb = $projectRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.client', 'client')
            ->addSelect('client')
            ->leftJoin('p.proposals', 'proposals')
            ->addSelect('proposals')
            ->orderBy('p.createdAt', 'DESC');

        // Apply filters
        if ($statusFilter) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $statusFilter);
        }

        if ($categoryFilter) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryFilter);
        }

        if ($clientFilter) {
            $qb->andWhere('client.id = :clientId')
                ->setParameter('clientId', $clientFilter);
        }

        if ($searchQuery) {
            $qb->andWhere('p.title LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $searchQuery . '%');
        }

        $projects = $qb->getQuery()->getResult();

        // Get filter options
        $categories = $categoryRepository->findAll();
        $clients = $usersRepository->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', 'client')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        $response = $this->render('admin_staff/projects/index.html.twig', [
            'projects' => $projects,
            'categories' => $categories,
            'clients' => $clients,
            'statusFilter' => $statusFilter,
            'categoryFilter' => $categoryFilter,
            'clientFilter' => $clientFilter,
            'searchQuery' => $searchQuery,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/{id}', name: 'app_admin_project_show', methods: ['GET'])]
    public function show(Project $project): Response
    {
        // Get proposals count
        $proposalsCount = $project->getProposals()->count();

        $response = $this->render('admin_staff/projects/show.html.twig', [
            'project' => $project,
            'proposalsCount' => $proposalsCount,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/{id}/delete', name: 'app_admin_project_delete', methods: ['POST'])]
    public function delete(Request $request, Project $project): Response
    {
        if ($this->isCsrfTokenValid('delete'.$project->getId(), $request->getPayload()->getString('_token'))) {
            // Check if project has associated proposals
            $proposalsCount = $project->getProposals()->count();
            
            if ($proposalsCount > 0) {
                $this->addFlash('error', "Cannot delete project '{$project->getTitle()}'. This project has {$proposalsCount} associated proposal(s). Please delete the proposals first.");
                return $this->redirectToRoute('app_admin_project_show', ['id' => $project->getId()], Response::HTTP_SEE_OTHER);
            }

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

                // Delete the project (no proposals to delete)
                $this->entityManager->remove($project);
                $this->entityManager->flush();

                $this->addFlash('success', 'Project deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'An error occurred while deleting the project: ' . $e->getMessage());
                error_log('Failed to delete project: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid security token.');
        }

        return $this->redirectToRoute('app_admin_project_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/change-status', name: 'app_admin_project_change_status', methods: ['POST'])]
    public function changeStatus(Request $request, Project $project): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!$newStatus || !in_array($newStatus, ['pending', 'active', 'ongoing', 'completed', 'cancelled'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid status.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $project->setStatus($newStatus);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Project status updated successfully.',
            'status' => $newStatus
        ]);
    }
}

