<?php

namespace App\Controller;

use App\Entity\Proposal;
use App\Entity\Users;
use App\Repository\ProposalRepository;
use App\Repository\ProjectRepository;
use App\Service\ActivityLogService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/designer/proposals')]
#[IsGranted('ROLE_DESIGNER')]
final class DesignerProposalsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ActivityLogService $activityLogService
    ) {}
    #[Route('/', name: 'app_designer_proposals', methods: ['GET'])]
    public function index(
        ProposalRepository $proposalRepository,
        ProjectRepository $projectRepository,
        Request $request
    ): Response {
        /** @var Users $designer */
        $designer = $this->getUser();
        
        if (!$designer) {
            return $this->redirectToRoute('app_login');
        }

        // Get filter parameters
        $statusFilter = $request->query->get('status', '');
        $projectFilter = $request->query->get('project', '');
        $searchQuery = $request->query->get('search', '');

        // Build query - only proposals from logged-in designer
        $qb = $proposalRepository->createQueryBuilder('pr')
            ->leftJoin('pr.project', 'p')
            ->addSelect('p')
            ->leftJoin('p.client', 'client')
            ->addSelect('client')
            ->leftJoin('p.category', 'c')
            ->addSelect('c')
            ->where('pr.designer = :designer')
            ->setParameter('designer', $designer)
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

        if ($searchQuery) {
            $qb->andWhere('pr.proposalText LIKE :search OR pr.coverLetter LIKE :search OR p.title LIKE :search')
                ->setParameter('search', '%' . $searchQuery . '%');
        }

        $proposals = $qb->getQuery()->getResult();

        // Get projects that this designer has proposals for (for filter dropdown)
        $designerProjects = $projectRepository->createQueryBuilder('p')
            ->innerJoin('p.proposals', 'pr')
            ->where('pr.designer = :designer')
            ->setParameter('designer', $designer)
            ->orderBy('p.title', 'ASC')
            ->getQuery()
            ->getResult();

        $response = $this->render('designer/designer_proposals/index.html.twig', [
            'proposals' => $proposals,
            'projects' => $designerProjects,
            'statusFilter' => $statusFilter,
            'projectFilter' => $projectFilter,
            'searchQuery' => $searchQuery,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/{id}', name: 'app_designer_proposal_show', methods: ['GET'])]
    public function show(
        Proposal $proposal,
        Request $request
    ): Response {
        /** @var Users $designer */
        $designer = $this->getUser();
        
        if (!$designer) {
            return $this->redirectToRoute('app_login');
        }

        // Verify proposal belongs to logged-in designer
        if ($proposal->getDesigner() !== $designer) {
            throw $this->createAccessDeniedException('You do not have access to this proposal.');
        }

        // Check if this is an AJAX request (for modal)
        if ($request->isXmlHttpRequest() || $request->query->get('modal') === '1') {
            return $this->render('designer/designer_proposals/view_modal.html.twig', [
                'proposal' => $proposal,
            ]);
        }

        // Regular page request (fallback)
        return $this->render('designer/designer_proposals/show.html.twig', [
            'proposal' => $proposal,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_designer_proposal_edit', methods: ['GET'])]
    public function edit(
        Proposal $proposal,
        Request $request
    ): Response {
        /** @var Users $designer */
        $designer = $this->getUser();
        
        if (!$designer) {
            return $this->redirectToRoute('app_login');
        }

        // Verify proposal belongs to logged-in designer
        if ($proposal->getDesigner() !== $designer) {
            throw $this->createAccessDeniedException('You do not have access to this proposal.');
        }

        // Only allow editing if status is pending
        if ($proposal->getStatus() !== 'pending') {
            return new JsonResponse([
                'success' => false,
                'message' => 'You can only edit proposals with pending status.'
            ], 400);
        }

        // Check if this is an AJAX request (for modal)
        if ($request->isXmlHttpRequest() || $request->query->get('modal') === '1') {
            return $this->render('designer/designer_proposals/edit_modal.html.twig', [
                'proposal' => $proposal,
            ]);
        }

        // Regular page request (fallback)
        return $this->render('designer/designer_proposals/edit.html.twig', [
            'proposal' => $proposal,
        ]);
    }

    #[Route('/{id}/update', name: 'app_designer_proposal_update', methods: ['POST'])]
    public function update(
        Proposal $proposal,
        Request $request,
        NotificationService $notificationService
    ): JsonResponse {
        /** @var Users $designer */
        $designer = $this->getUser();
        
        if (!$designer) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You must be logged in to update a proposal.'
            ], 401);
        }

        // Verify proposal belongs to logged-in designer
        if ($proposal->getDesigner() !== $designer) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You do not have access to this proposal.'
            ], 403);
        }

        // Only allow editing if status is pending
        if ($proposal->getStatus() !== 'pending') {
            return new JsonResponse([
                'success' => false,
                'message' => 'You can only edit proposals with pending status.'
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

        // Update proposal
        $proposal->setProposalText($proposalText);
        $proposal->setProposedPrice($proposedPrice);
        $proposal->setDeliveryTime($deliveryTime);
        $proposal->setUpdatedAt(new \DateTime());
        
        if ($coverLetter !== null) {
            $proposal->setCoverLetter($coverLetter);
        }
        
        $proposal->setRevisionRounds($revisionRounds);

        $this->entityManager->flush();

        // Manually log the update (fallback if event subscriber doesn't fire)
        try {
            $this->activityLogService->log(
                'UPDATE',
                'Proposal',
                $proposal->getId(),
                "Updated Proposal #" . $proposal->getId()
            );
        } catch (\Exception $e) {
            error_log('Failed to log proposal update: ' . $e->getMessage());
        }

        // Notify admins about proposal update
        try {
            $notificationService->notifyProposalUpdate($proposal);
        } catch (\Exception $e) {
            // Log error but don't fail proposal update
            error_log('Failed to send notification for proposal update: ' . $e->getMessage());
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Proposal updated successfully!',
            'proposalId' => $proposal->getId()
        ]);
    }

    #[Route('/{id}/delete', name: 'app_designer_proposal_delete', methods: ['POST'])]
    public function delete(
        Proposal $proposal,
        NotificationService $notificationService
    ): JsonResponse {
        /** @var Users $designer */
        $designer = $this->getUser();
        
        if (!$designer) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You must be logged in to delete a proposal.'
            ], 401);
        }

        // Verify proposal belongs to logged-in designer
        if ($proposal->getDesigner() !== $designer) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You do not have access to this proposal.'
            ], 403);
        }

        // Only allow deletion if status is pending (optional - you can remove this check if you want to allow deletion of any status)
        // For now, let's allow deletion of any proposal by the designer
        // if ($proposal->getStatus() !== 'pending') {
        //     return new JsonResponse([
        //         'success' => false,
        //         'message' => 'You can only delete proposals with pending status.'
        //     ], 400);
        // }

        // Store proposal info for notification before deletion
        $proposalId = $proposal->getId();
        $projectTitle = $proposal->getProject() ? $proposal->getProject()->getTitle() : 'Unknown Project';

        // Log deletion before removing (preRemove event will also fire, but this ensures it's logged)
        try {
            $this->activityLogService->log(
                'DELETE',
                'Proposal',
                $proposalId,
                "Deleted Proposal #" . $proposalId
            );
        } catch (\Exception $e) {
            error_log('Failed to log proposal deletion: ' . $e->getMessage());
        }

        // Notify admins about proposal deletion (before deletion)
        try {
            $notificationService->notifyProposalDelete($proposal);
        } catch (\Exception $e) {
            // Log error but don't fail proposal deletion
            error_log('Failed to send notification for proposal deletion: ' . $e->getMessage());
        }

        // Delete the proposal
        $this->entityManager->remove($proposal);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Proposal deleted successfully!',
            'proposalId' => $proposalId
        ]);
    }
}
