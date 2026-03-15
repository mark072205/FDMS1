<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Entity\Users;
use App\Entity\Project;
use App\Entity\Proposal;
use App\Repository\NotificationRepository;
use App\Repository\UsersRepository;
use App\Repository\ProjectRepository;
use App\Repository\ProposalRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/notifications')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private UsersRepository $usersRepository,
        private ProjectRepository $projectRepository,
        private ProposalRepository $proposalRepository,
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/', name: 'app_admin_notification_index', methods: ['GET'])]
    public function index(): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_login');
        }
        
        // Get admin notifications (where user IS NULL)
        $notifications = $this->notificationRepository->findRecentAdminNotifications(50);
        $unreadCount = $this->notificationRepository->getAdminUnreadCount();
        
        // Add user data to each notification for template rendering
        $notificationsWithUsers = [];
        foreach ($notifications as $notification) {
            $relatedUser = $this->getRelatedUser($notification);
            $notificationsWithUsers[] = [
                'notification' => $notification,
                'relatedUser' => $relatedUser,
            ];
        }

        return $this->render('admin_staff/notifications/index.html.twig', [
            'notifications' => $notifications,
            'notificationsWithUsers' => $notificationsWithUsers,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/list', name: 'app_admin_notification_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $authenticatedUser = $this->getUser();
            
            if (!$this->isGranted('ROLE_ADMIN')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Authentication required.',
                    'notifications' => [],
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Get admin notifications (where user IS NULL)
            $limit = (int) $request->query->get('limit', 15);
            $notifications = $this->notificationRepository->findRecentAdminNotifications($limit);

            $data = [];
            foreach ($notifications as $notification) {
                try {
                    $relatedUser = $this->getRelatedUser($notification);
                    $userData = null;
                    
                    if ($relatedUser) {
                        $profilePicture = $this->normalizeProfilePicturePath($relatedUser->getProfilePicture());
                        
                        $userData = [
                            'id' => $relatedUser->getId(),
                            'firstName' => $relatedUser->getFirstName(),
                            'lastName' => $relatedUser->getLastName(),
                            'username' => $relatedUser->getUsername(),
                            'profilePicture' => $profilePicture,
                            'userType' => $relatedUser->getUserType(),
                        ];
                    }
                    
                    // Always include user data if available, otherwise null
                    $data[] = [
                        'id' => $notification->getId(),
                        'type' => $notification->getType(),
                        'title' => $notification->getTitle(),
                        'message' => $notification->getMessage(),
                        'isRead' => $notification->isRead(),
                        'createdAt' => $notification->getCreatedAt()->format('Y-m-d H:i:s'),
                        'createdAtFormatted' => $this->formatRelativeTime($notification->getCreatedAt()),
                        'actionUrl' => $notification->getActionUrl(),
                        'user' => $userData, // User data for avatar display
                        'triggeredByUserAvatar' => $userData ? ($userData['profilePicture'] ? '/uploads/profile_pictures/' . $userData['profilePicture'] : null) : null,
                        'triggeredByUserInitials' => $userData ? strtoupper(substr($userData['firstName'], 0, 1) . substr($userData['lastName'], 0, 1)) : '??',
                    ];
                } catch (\Exception $e) {
                    // Skip invalid notifications
                    continue;
                }
            }

            return new JsonResponse([
                'success' => true,
                'notifications' => $data,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'notifications' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/unread-count', name: 'app_admin_notification_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        try {
            $authenticatedUser = $this->getUser();
            
            if (!$this->isGranted('ROLE_ADMIN')) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Authentication required.',
                    'count' => 0,
                ], Response::HTTP_UNAUTHORIZED);
            }
            
            // Get admin unread count (where user IS NULL)
            $count = $this->notificationRepository->getAdminUnreadCount();

            return new JsonResponse([
                'success' => true,
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'An error occurred: ' . $e->getMessage(),
                'count' => 0,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/{id}/read', name: 'app_admin_notification_mark_read', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function markAsRead(Notification $notification): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verify notification is an admin notification (user IS NULL)
        if ($notification->getUser() !== null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Unauthorized access to notification.',
            ], Response::HTTP_FORBIDDEN);
        }

        $notification->setIsRead(true);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Notification marked as read.',
        ]);
    }

    #[Route('/mark-all-read', name: 'app_admin_notification_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        // Mark all admin notifications as read (where user IS NULL)
        $count = $this->notificationRepository->markAllAdminAsRead();

        return new JsonResponse([
            'success' => true,
            'message' => 'All notifications marked as read.',
            'count' => $count,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_notification_delete', requirements: ['id' => '\d+'], methods: ['DELETE'])]
    public function delete(Notification $notification): JsonResponse
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Verify notification is an admin notification (user IS NULL)
        if ($notification->getUser() !== null) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Unauthorized access to notification.',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->entityManager->remove($notification);
        $this->entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Notification deleted.',
        ]);
    }


    /**
     * Get the user who triggered the notification based on notification type and related entity
     * Always tries to find a user to display their profile/avatar
     */
    private function getRelatedUser(Notification $notification): ?Users
    {
        $relatedEntityType = $notification->getRelatedEntityType();
        $relatedEntityId = $notification->getRelatedEntityId();
        $notificationType = $notification->getType();
        $message = $notification->getMessage();
        
        // First, try to get user from related entity
        if ($relatedEntityType && $relatedEntityId) {
            try {
                switch ($relatedEntityType) {
                    case 'Users':
                        // Direct user reference (new_user, profile_picture_changed, user_status_changed, project_deleted, proposal_deleted)
                        $user = $this->usersRepository->find($relatedEntityId);
                        if ($user) {
                            return $user;
                        }
                        break;
                        
                    case 'Project':
                        // Get client from project (new_project, project_updated)
                        // For project_deleted, relatedEntityType should be 'Users' (fixed in newer notifications)
                        // But handle old notifications that might still have 'Project' type
                        if ($notificationType === 'project_deleted') {
                            // For deleted projects, the project no longer exists
                            // Skip trying to find the project and let message extraction handle it
                            break;
                        }
                        $project = $this->projectRepository->find($relatedEntityId);
                        if ($project && $project->getClient()) {
                            return $project->getClient();
                        }
                        break;
                        
                    case 'Proposal':
                        // Get designer from proposal (new_proposal, proposal_updated)
                        // For proposal_deleted, relatedEntityType should be 'Users' (fixed in newer notifications)
                        // But handle old notifications that might still have 'Proposal' type
                        if ($notificationType === 'proposal_deleted') {
                            // For deleted proposals, try to find user by ID if it's actually a user ID
                            $user = $this->usersRepository->find($relatedEntityId);
                            if ($user) {
                                return $user;
                            }
                        }
                        $proposal = $this->proposalRepository->find($relatedEntityId);
                        if ($proposal && $proposal->getDesigner()) {
                            return $proposal->getDesigner();
                        }
                        break;
                }
            } catch (\Exception $e) {
                // Log error but continue trying other methods
                error_log('Error fetching related user from entity: ' . $e->getMessage());
            }
        }
        
        // If we couldn't find user from related entity, try to extract from message
        // Messages typically contain names like "John Doe created..." or "Jane Smith deleted..."
        if ($message) {
            try {
                // Try to extract name from message (format: "FirstName LastName action...")
                // Pattern: Start of message, capture name (words before first verb)
                // Updated pattern to handle more cases and be more flexible
                if (preg_match('/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\s+(created|deleted|updated|submitted|changed|enabled|disabled|verified|unverified|has\s+registered|uploaded)/i', $message, $matches)) {
                    $nameParts = explode(' ', trim($matches[1]));
                    if (count($nameParts) >= 2) {
                        $firstName = trim($nameParts[0]);
                        $lastName = trim($nameParts[1]);
                        
                        // Try exact match first
                        $user = $this->usersRepository->findOneBy([
                            'firstName' => $firstName,
                            'lastName' => $lastName
                        ]);
                        if ($user) {
                            return $user;
                        }
                        
                        // Try case-insensitive search if exact match fails
                        $qb = $this->usersRepository->createQueryBuilder('u');
                        $user = $qb->where('LOWER(u.firstName) = LOWER(:firstName)')
                            ->andWhere('LOWER(u.lastName) = LOWER(:lastName)')
                            ->setParameter('firstName', $firstName)
                            ->setParameter('lastName', $lastName)
                            ->setMaxResults(1)
                            ->getQuery()
                            ->getOneOrNullResult();
                        if ($user) {
                            return $user;
                        }
                    }
                }
                
                // Alternative: Try to extract username if message contains "@username" format
                if (preg_match('/@([a-zA-Z0-9_]+)/', $message, $matches)) {
                    $username = $matches[1];
                    $user = $this->usersRepository->findOneBy(['username' => $username]);
                    if ($user) {
                        return $user;
                    }
                }
            } catch (\Exception $e) {
                // Log error but don't fail
                error_log('Error extracting user from message: ' . $e->getMessage());
            }
        }
        
        // If all methods failed, return null (template will show icon as fallback)
        return null;
    }

    /**
     * Normalize profile picture path to just the filename
     * Handles both full paths from File entity and simple filenames
     */
    private function normalizeProfilePicturePath(?string $profilePicture): ?string
    {
        if (!$profilePicture) {
            return null;
        }
        
        // If it's already just a filename (no slashes), return as-is
        if (strpos($profilePicture, '/') === false && strpos($profilePicture, '\\') === false) {
            return $profilePicture;
        }
        
        // Extract filename from path (handle both forward and backslashes)
        $filename = basename($profilePicture);
        
        // Remove any directory prefixes like "uploads/profile_pictures/" if present
        $filename = str_replace('uploads/profile_pictures/', '', $filename);
        $filename = str_replace('uploads\\profile_pictures\\', '', $filename);
        
        return $filename ?: null;
    }

    /**
     * Format datetime as relative time (e.g., "2 hours ago", "3 days ago")
     */
    private function formatRelativeTime(\DateTimeImmutable $dateTime): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($dateTime);

        if ($diff->days > 7) {
            return $dateTime->format('M d, Y');
        } elseif ($diff->days > 0) {
            return $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago';
        } elseif ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
        } else {
            return 'Just now';
        }
    }
}

