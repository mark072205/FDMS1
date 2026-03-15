<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Users;
use App\Entity\Project;
use App\Entity\Proposal;
use App\Entity\File;
use App\Repository\NotificationRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * NotificationService - Notifies admins about all user activities across the system
 * 
 * This service monitors and notifies all admin users about:
 * - User registrations and account activities
 * - Project creation and updates
 * - Proposal submissions and changes
 * - User status changes (active/inactive, verified/unverified)
 * - File uploads and system activities
 */
class NotificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private UsersRepository $usersRepository
    ) {}

    /**
     * Create a notification for a specific user
     * If user is null, the notification is for in-memory admins
     */
    public function createNotification(
        ?Users $user,
        string $type,
        string $title,
        string $message,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?string $actionUrl = null
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user); // null for admin notifications
        $notification->setType($type);
        $notification->setTitle($title);
        $notification->setMessage($message);
        $notification->setRelatedEntityType($relatedEntityType);
        $notification->setRelatedEntityId($relatedEntityId);
        $notification->setActionUrl($actionUrl);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Notify all admin users about user activity
     * 
     * This method broadcasts notifications to all in-memory admin users.
     * Notifications are stored with user = null to indicate they are for admins.
     */
    public function notifyAllAdmins(
        string $type,
        string $title,
        string $message,
        ?string $relatedEntityType = null,
        ?int $relatedEntityId = null,
        ?string $actionUrl = null
    ): void {
        // Create notification with null user for in-memory admins
        // All in-memory admins will see notifications where user IS NULL
        $this->createNotification(
            null, // null user = admin notifications
            $type,
            $title,
            $message,
            $relatedEntityType,
            $relatedEntityId,
            $actionUrl
        );
    }

    /**
     * Notify admins when a new user registers
     * Monitors: All user registrations (clients, designers, admins)
     */
    public function notifyNewUser(Users $newUser): void
    {
        $role = $this->getUserRoleLabel($newUser);
        $title = "New User Registration";
        $message = sprintf(
            "%s (%s) has registered on the platform.",
            $newUser->getUsername(),
            $role
        );
        $actionUrl = "/admin/users/{$newUser->getId()}";

        $this->notifyAllAdmins(
            'new_user',
            $title,
            $message,
            'Users',
            $newUser->getId(),
            $actionUrl
        );
    }

    /**
     * Notify admins when a new project is created
     * Monitors: All project creation activities by clients
     */
    public function notifyNewProject(Project $project): void
    {
        $client = $project->getClient();
        $clientName = $client ? $client->getFirstName() . ' ' . $client->getLastName() : 'Unknown';
        $title = "New Project Created";
        $message = sprintf(
            "%s created a new project: \"%s\"",
            $clientName,
            $project->getTitle()
        );
        $actionUrl = "/admin/projects/{$project->getId()}";

        $this->notifyAllAdmins(
            'new_project',
            $title,
            $message,
            'Project',
            $project->getId(),
            $actionUrl
        );
    }

    /**
     * Notify admins when a new proposal is submitted
     * Monitors: All proposal submissions by designers
     */
    public function notifyNewProposal(Proposal $proposal): void
    {
        $designer = $proposal->getDesigner();
        $project = $proposal->getProject();
        $designerName = $designer ? $designer->getFirstName() . ' ' . $designer->getLastName() : 'Unknown';
        $projectTitle = $project ? $project->getTitle() : 'Unknown Project';
        
        $title = "New Proposal Submitted";
        $message = sprintf(
            "%s submitted a proposal for project: \"%s\"",
            $designerName,
            $projectTitle
        );
        $actionUrl = "/admin/proposals/{$proposal->getId()}";

        $this->notifyAllAdmins(
            'new_proposal',
            $title,
            $message,
            'Proposal',
            $proposal->getId(),
            $actionUrl
        );
    }

    /**
     * Notify admins when user status changes
     * Monitors: User account status changes (enabled/disabled, verified/unverified)
     */
    public function notifyUserStatusChange(Users $user, string $changeType, ?string $changedBy = null): void
    {
        $changedByName = $changedBy ?: 'System';
        $title = "User Status Changed";
        
        $messages = [
            'enabled' => sprintf("%s enabled user: %s", $changedByName, $user->getUsername()),
            'disabled' => sprintf("%s disabled user: %s", $changedByName, $user->getUsername()),
            'verified' => sprintf("%s verified user: %s", $changedByName, $user->getUsername()),
            'unverified' => sprintf("%s unverified user: %s", $changedByName, $user->getUsername()),
        ];
        
        $message = $messages[$changeType] ?? sprintf("%s changed status for user: %s", $changedByName, $user->getUsername());
        $actionUrl = "/admin/users/{$user->getId()}";

        $this->notifyAllAdmins(
            'user_status_changed',
            $title,
            $message,
            'Users',
            $user->getId(),
            $actionUrl
        );
    }

    /**
     * Notify admins when a large or suspicious file is uploaded
     * Monitors: File upload activities (large files, suspicious types)
     */
    public function notifyFileUpload(File $file, ?string $reason = null): void
    {
        $uploader = $file->getUploadedBy();
        $uploaderName = $uploader ? $uploader->getUsername() : 'Unknown';
        $fileSize = $this->formatFileSize($file->getSize());
        
        $title = $reason ? "Suspicious File Upload" : "Large File Uploaded";
        $message = sprintf(
            "File \"%s\" (%s) uploaded by %s%s",
            $file->getFilename(),
            $fileSize,
            $uploaderName,
            $reason ? " - {$reason}" : ""
        );
        $actionUrl = "/admin/files";

        $this->notifyAllAdmins(
            'file_uploaded',
            $title,
            $message,
            'File',
            $file->getId(),
            $actionUrl
        );
    }

    /**
     * Notify admins when a user changes their profile picture
     * Monitors: Profile picture uploads/changes by any user
     */
    public function notifyProfilePictureChange(Users $user): void
    {
        $role = $this->getUserRoleLabel($user);
        $title = "Profile Picture Changed";
        $message = sprintf(
            "%s (%s) changed their profile picture.",
            $user->getUsername(),
            $role
        );
        $actionUrl = "/admin/users/{$user->getId()}";

        $this->notifyAllAdmins(
            'profile_picture_changed',
            $title,
            $message,
            'Users',
            $user->getId(),
            $actionUrl
        );
    }

    /**
     * Notify admins when a project is updated/edited
     * Monitors: Project edit/update activities by clients
     */
    public function notifyProjectUpdate(Project $project): void
    {
        $client = $project->getClient();
        $clientName = $client ? $client->getFirstName() . ' ' . $client->getLastName() : 'Unknown';
        $title = "Project Updated";
        $message = sprintf(
            "%s updated project: \"%s\"",
            $clientName,
            $project->getTitle()
        );
        $actionUrl = "/admin/projects/{$project->getId()}";

        $this->notifyAllAdmins(
            'project_updated',
            $title,
            $message,
            'Project',
            $project->getId(),
            $actionUrl
        );
    }

    /**
     * Notify admins when a project is deleted
     * Monitors: Project deletion activities by clients
     */
    public function notifyProjectDelete(Project $project): void
    {
        $client = $project->getClient();
        $clientName = $client ? $client->getFirstName() . ' ' . $client->getLastName() : 'Unknown';
        $title = "Project Deleted";
        $message = sprintf(
            "%s deleted project: \"%s\"",
            $clientName,
            $project->getTitle()
        );
        $actionUrl = "/admin/projects";

        // Store client ID instead of project ID since project will be deleted
        $this->notifyAllAdmins(
            'project_deleted',
            $title,
            $message,
            'Users', // Use 'Users' type so we can find the client even after project deletion
            $client ? $client->getId() : null,
            $actionUrl
        );
    }

    /**
     * Notify admins when a proposal is updated/edited
     * Monitors: Proposal edit/update activities by designers
     */
    public function notifyProposalUpdate(Proposal $proposal): void
    {
        $designer = $proposal->getDesigner();
        $project = $proposal->getProject();
        $designerName = $designer ? $designer->getFirstName() . ' ' . $designer->getLastName() : 'Unknown';
        $projectTitle = $project ? $project->getTitle() : 'Unknown Project';
        
        $title = "Proposal Updated";
        $message = sprintf(
            "%s updated their proposal for project: \"%s\"",
            $designerName,
            $projectTitle
        );
        $actionUrl = "/admin/proposals/{$proposal->getId()}";

        $this->notifyAllAdmins(
            'proposal_updated',
            $title,
            $message,
            'Proposal',
            $proposal->getId(),
            $actionUrl
        );
    }

    /**
     * Notify admins when a proposal is deleted
     * Monitors: Proposal deletion activities by designers
     */
    public function notifyProposalDelete(Proposal $proposal): void
    {
        $designer = $proposal->getDesigner();
        $project = $proposal->getProject();
        $designerName = $designer ? $designer->getFirstName() . ' ' . $designer->getLastName() : 'Unknown';
        $projectTitle = $project ? $project->getTitle() : 'Unknown Project';
        
        $title = "Proposal Deleted";
        $message = sprintf(
            "%s deleted their proposal for project: \"%s\"",
            $designerName,
            $projectTitle
        );
        $actionUrl = $project ? "/admin/projects/{$project->getId()}" : "/admin/proposals";

        // Store designer ID instead of proposal ID since proposal will be deleted
        $this->notifyAllAdmins(
            'proposal_deleted',
            $title,
            $message,
            'Users', // Use 'Users' type so we can find the designer even after proposal deletion
            $designer ? $designer->getId() : null,
            $actionUrl
        );
    }

    /**
     * Get user role label for display
     */
    private function getUserRoleLabel(Users $user): string
    {
        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles)) {
            return 'Admin';
        } elseif (in_array('ROLE_DESIGNER', $roles)) {
            return 'Designer';
        } elseif (in_array('ROLE_CLIENT', $roles)) {
            return 'Client';
        }
        return 'User';
    }

    /**
     * Format file size for display
     */
    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

