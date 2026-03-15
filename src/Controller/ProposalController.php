<?php

namespace App\Controller;

use App\Entity\Proposal;
use App\Repository\ProposalRepository;
use App\Repository\ProjectRepository;
use App\Repository\UsersRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/proposals')]
final class ProposalController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {}

    #[Route('/', name: 'app_admin_proposal_index', methods: ['GET'])]
    public function index(
        ProposalRepository $proposalRepository,
        ProjectRepository $projectRepository,
        UsersRepository $usersRepository,
        Request $request
    ): Response {
        // Get filter parameters
        $statusFilter = $request->query->get('status', '');
        $projectFilter = $request->query->get('project', '');
        $designerFilter = $request->query->get('designer', '');
        $searchQuery = $request->query->get('search', '');

        // Build query
        $qb = $proposalRepository->createQueryBuilder('pr')
            ->leftJoin('pr.project', 'p')
            ->addSelect('p')
            ->leftJoin('pr.designer', 'd')
            ->addSelect('d')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->orderBy('pr.createdAt', 'DESC');

        // Apply filters
        if ($statusFilter) {
            $qb->andWhere('pr.status = :status')
                ->setParameter('status', $statusFilter);
        }

        if ($projectFilter) {
            $qb->andWhere('p.id = :projectId')
                ->setParameter('projectId', $projectFilter);
        }

        if ($designerFilter) {
            $qb->andWhere('d.id = :designerId')
                ->setParameter('designerId', $designerFilter);
        }

        if ($searchQuery) {
            $qb->andWhere('pr.proposalText LIKE :search OR pr.coverLetter LIKE :search OR p.title LIKE :search OR d.username LIKE :search')
                ->setParameter('search', '%' . $searchQuery . '%');
        }

        $proposals = $qb->getQuery()->getResult();

        // Get filter options
        $projects = $projectRepository->findAll();
        $designers = $usersRepository->createQueryBuilder('u')
            ->where('u.role = :role')
            ->setParameter('role', 'designer')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('admin_staff/proposals/index.html.twig', [
            'proposals' => $proposals,
            'projects' => $projects,
            'designers' => $designers,
            'statusFilter' => $statusFilter,
            'projectFilter' => $projectFilter,
            'designerFilter' => $designerFilter,
            'searchQuery' => $searchQuery,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_proposal_show', methods: ['GET'])]
    public function show(Proposal $proposal): Response
    {
        return $this->render('admin_staff/proposals/show.html.twig', [
            'proposal' => $proposal,
        ]);
    }

    #[Route('/{id}/change-status', name: 'app_admin_proposal_change_status', methods: ['POST'])]
    public function changeStatus(Request $request, Proposal $proposal): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if (!$newStatus || !in_array($newStatus, ['pending', 'accepted', 'rejected'])) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid status provided.'
            ], 400);
        }

        $proposal->setStatus($newStatus);
        $proposal->setUpdatedAt(new \DateTime());
        
        if ($newStatus !== 'pending') {
            $proposal->setRespondedAt(new \DateTime());
        }

        if ($newStatus === 'rejected' && isset($data['rejectionReason'])) {
            $proposal->setRejectionReason($data['rejectionReason']);
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Proposal status updated successfully.',
            'status' => $newStatus
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_proposal_delete', methods: ['POST'])]
    public function delete(Proposal $proposal, Request $request): JsonResponse
    {
        // Only admins and staff can delete proposals
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_STAFF')) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You do not have permission to delete proposals. Only administrators and staff can delete proposals.'
            ], 403);
        }

        // Store proposal info for notification before deletion
        $proposalId = $proposal->getId();
        $projectTitle = $proposal->getProject() ? $proposal->getProject()->getTitle() : 'Unknown Project';
        $designerName = $proposal->getDesigner() ? $proposal->getDesigner()->getUsername() : 'Unknown Designer';

        // Notify admins about proposal deletion (before deletion)
        try {
            $this->notificationService->notifyProposalDelete($proposal);
        } catch (\Exception $e) {
            // Log error but don't fail proposal deletion
            error_log('Failed to send notification for proposal deletion: ' . $e->getMessage());
        }

        // Delete the proposal
        $this->entityManager->remove($proposal);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => "Proposal #{$proposalId} for project '{$projectTitle}' by {$designerName} has been deleted successfully."
        ]);
    }
}

