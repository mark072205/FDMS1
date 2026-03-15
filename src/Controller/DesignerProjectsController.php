<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Proposal;
use App\Entity\Users;
use App\Repository\ProjectRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProposalRepository;
use App\Service\ActivityLogService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/designer/projects')]
#[IsGranted('ROLE_DESIGNER')]
final class DesignerProjectsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityLogService $activityLogService
    ) {}
    #[Route('/', name: 'app_designer_projects', methods: ['GET'])]
    public function index(
        ProjectRepository $projectRepository,
        CategoryRepository $categoryRepository,
        ProposalRepository $proposalRepository,
        Request $request
    ): Response {
        /** @var Users $designer */
        $designer = $this->getUser();
        
        if (!$designer) {
            return $this->redirectToRoute('app_login');
        }

        // Get filter parameters
        $categoryFilter = $request->query->get('category', '');
        $statusFilter = $request->query->get('status', '');
        $budgetFilter = $request->query->get('budget', '');
        $searchQuery = $request->query->get('search', '');

        // Get categories for filters
        $categories = $categoryRepository->findAll();

        // Get projects where designer has already submitted proposals
        $existingProposals = $proposalRepository->createQueryBuilder('pr')
            ->select('IDENTITY(pr.project) as projectId')
            ->where('pr.designer = :designer')
            ->setParameter('designer', $designer)
            ->getQuery()
            ->getResult();

        $existingProposalProjectIds = array_column($existingProposals, 'projectId');

        // Build query for available projects
        $qb = $projectRepository->createQueryBuilder('p')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->leftJoin('p.client', 'client')
            ->addSelect('client')
            ->leftJoin('p.proposals', 'proposals')
            ->addSelect('proposals')
            ->where('p.status IN (:statuses)')
            ->setParameter('statuses', ['pending', 'active', 'ongoing']);

        // Exclude projects where designer already has a proposal
        if (!empty($existingProposalProjectIds)) {
            $qb->andWhere('p.id NOT IN (:existingProposals)')
                ->setParameter('existingProposals', $existingProposalProjectIds);
        }

        // Apply filters
        if ($categoryFilter) {
            $qb->andWhere('c.id = :categoryId')
                ->setParameter('categoryId', $categoryFilter);
        }

        if ($statusFilter) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $statusFilter);
        }

        if ($budgetFilter) {
            if ($budgetFilter === '0-500') {
                $qb->andWhere('p.budget >= 0 AND p.budget <= 500');
            } elseif ($budgetFilter === '500-1000') {
                $qb->andWhere('p.budget > 500 AND p.budget <= 1000');
            } elseif ($budgetFilter === '1000-5000') {
                $qb->andWhere('p.budget > 1000 AND p.budget <= 5000');
            } elseif ($budgetFilter === '5000+') {
                $qb->andWhere('p.budget > 5000');
            }
        }

        if ($searchQuery) {
            $qb->andWhere('p.title LIKE :search OR p.description LIKE :search')
                ->setParameter('search', '%' . $searchQuery . '%');
        }

        $qb->orderBy('p.createdAt', 'DESC');
        $projects = $qb->getQuery()->getResult();

        $response = $this->render('designer/designer_projects/index.html.twig', [
            'projects' => $projects,
            'categories' => $categories,
            'categoryFilter' => $categoryFilter,
            'statusFilter' => $statusFilter,
            'budgetFilter' => $budgetFilter,
            'searchQuery' => $searchQuery,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/{id}', name: 'app_designer_project_show', methods: ['GET'])]
    public function show(
        Project $project,
        ProposalRepository $proposalRepository,
        Request $request
    ): Response {
        /** @var Users $designer */
        $designer = $this->getUser();
        
        if (!$designer) {
            return $this->redirectToRoute('app_login');
        }

        // Check if designer already has a proposal for this project
        $existingProposal = $proposalRepository->findOneBy([
            'project' => $project,
            'designer' => $designer
        ]);

        // Get proposals count for this project
        $proposalsCount = count($project->getProposals());

        // Check if this is an AJAX request (for modal)
        if ($request->isXmlHttpRequest() || $request->query->get('modal') === '1') {
            return $this->render('designer/designer_projects/modal.html.twig', [
                'project' => $project,
                'existingProposal' => $existingProposal,
                'proposalsCount' => $proposalsCount,
            ]);
        }

        // Regular page request (fallback)
        return $this->render('designer/designer_projects/show.html.twig', [
            'project' => $project,
            'existingProposal' => $existingProposal,
            'proposalsCount' => $proposalsCount,
        ]);
    }

    #[Route('/{id}/proposal/create', name: 'app_designer_proposal_create', methods: ['POST'])]
    public function createProposal(
        Project $project,
        ProposalRepository $proposalRepository,
        Request $request,
        NotificationService $notificationService
    ): JsonResponse {
        /** @var Users $designer */
        $designer = $this->getUser();
        
        if (!$designer) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You must be logged in to submit a proposal.'
            ], 401);
        }

        // Check if designer already has a proposal for this project
        $existingProposal = $proposalRepository->findOneBy([
            'project' => $project,
            'designer' => $designer
        ]);

        if ($existingProposal) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You have already submitted a proposal for this project.'
            ], 400);
        }

        // Check if project is in a valid status
        if (!in_array($project->getStatus(), ['pending', 'active', 'ongoing'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'This project is no longer accepting proposals.'
            ], 400);
        }

        // Get and validate form data
        $data = json_decode($request->getContent(), true);

        $proposalText = trim($data['proposalText'] ?? '');
        $proposedPrice = isset($data['proposedPrice']) ? (float)$data['proposedPrice'] : null;
        $deliveryTime = isset($data['deliveryTime']) ? (int)$data['deliveryTime'] : null;
        $coverLetter = isset($data['coverLetter']) ? trim($data['coverLetter']) : null;
        $revisionRounds = isset($data['revisionRounds']) ? (int)$data['revisionRounds'] : 1;

        // Validation
        if (empty($proposalText)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Proposal text is required.'
            ], 400);
        }

        if ($proposedPrice === null || $proposedPrice <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Proposed price must be a positive number.'
            ], 400);
        }

        if ($deliveryTime === null || $deliveryTime <= 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Delivery time must be a positive number of days.'
            ], 400);
        }

        if ($revisionRounds < 0) {
            $revisionRounds = 1;
        }

        // Create proposal
        $proposal = new Proposal();
        $proposal->setProject($project);
        $proposal->setDesigner($designer);
        $proposal->setProposalText($proposalText);
        $proposal->setProposedPrice($proposedPrice);
        $proposal->setDeliveryTime($deliveryTime);
        $proposal->setStatus('pending');
        
        if ($coverLetter) {
            $proposal->setCoverLetter($coverLetter);
        }
        
        $proposal->setRevisionRounds($revisionRounds);

        $this->entityManager->persist($proposal);
        $this->entityManager->flush();

        // Manually log the creation (fallback if event subscriber doesn't fire)
        try {
            $this->activityLogService->log(
                'CREATE',
                'Proposal',
                $proposal->getId(),
                "Created Proposal #" . $proposal->getId()
            );
        } catch (\Exception $e) {
            error_log('Failed to log proposal creation: ' . $e->getMessage());
        }

        // Notify admins about new proposal
        try {
            $notificationService->notifyNewProposal($proposal);
        } catch (\Exception $e) {
            // Log error but don't fail proposal creation
            error_log('Failed to send notification for new proposal: ' . $e->getMessage());
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Proposal submitted successfully!',
            'proposalId' => $proposal->getId()
        ]);
    }
}
