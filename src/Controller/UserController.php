<?php

namespace App\Controller;

use App\Entity\Users;
use App\Form\UsersType;
use App\Form\StaffSettingsType;
use App\Repository\UsersRepository;
use App\Service\NotificationService;
use App\Service\SecurityConfigService;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/users')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private NotificationService $notificationService,
        private SecurityConfigService $securityConfigService,
        private ValidatorInterface $validator,
        private ActivityLogService $activityLogService
    ) {}

    #[Route('/', name: 'app_user_index', methods: ['GET'])]
    public function index(UsersRepository $usersRepository, Request $request): Response
    {
        // Note: Staff users can access this page but will see a restricted access overlay in the template
        // Get filter parameters
        $userTypeFilter = $request->query->get('userType', '');
        $statusFilter = $request->query->get('status', '');
        $verifiedFilter = $request->query->get('verified', '');

        // Build query - include all users (admin, staff, client, designer)
        $qb = $usersRepository->createQueryBuilder('u');

        // Apply filters
        if ($userTypeFilter) {
            $qb->andWhere('u.userType = :userType')
                ->setParameter('userType', $userTypeFilter);
        }

        if ($statusFilter !== '') {
            $isActive = $statusFilter === 'active';
            $qb->andWhere('u.isActive = :isActive')
                ->setParameter('isActive', $isActive);
        }

        if ($verifiedFilter !== '') {
            $isVerified = $verifiedFilter === 'verified';
            $qb->andWhere('u.verified = :verified')
                ->setParameter('verified', $isVerified);
        }

        $qb->orderBy('u.createdAt', 'ASC');
        
        $users = $qb->getQuery()->getResult();
        
        $response = $this->render('admin_staff/users/index.html.twig', [
            'users' => $users,
            'userTypeFilter' => $userTypeFilter,
            'statusFilter' => $statusFilter,
            'verifiedFilter' => $verifiedFilter,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }


    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, UsersRepository $usersRepository): Response
    {
        // Prevent staff users from creating users - only admins can create users
        $currentUser = $this->getUser();
        if ($currentUser instanceof Users && ($currentUser->getRole() === 'staff' || $currentUser->getUserType() === 'staff')) {
            $this->addFlash('error', 'You do not have permission to create users. Only administrators can create users.');
            return $this->redirectToRoute('app_dashboard');
        }

        // Check if current user is an admin (can create admin users)
        // Any admin user (role='admin', userType='admin') can create other admin users
        $isAdmin = false;
        if ($currentUser instanceof Users) {
            $isAdmin = ($currentUser->getRole() === 'admin' && $currentUser->getUserType() === 'admin');
        }

        $user = new Users();
        $form = $this->createForm(UsersType::class, $user, [
            'allow_admin_creation' => $isAdmin
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Get userType before validation to determine validation groups
            $userType = $form->get('userType')->getData();
            
            // Get the plain password from the form before validation
            $plainPassword = $form->get('password')->getData();
            $confirmPassword = $form->get('confirmPassword')->getData();
            
            // Validate password confirmation first
            if ($plainPassword !== $confirmPassword) {
                $this->addFlash('error', 'Passwords do not match. Please try again.');
                return $this->render('admin_staff/users/new.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }
            
            // Set validation groups based on user type (skip password_strict for admin/staff)
            $validationGroups = ['Default', 'password_required'];
            if ($userType !== 'admin' && $userType !== 'staff') {
                $validationGroups[] = 'password_strict';
            }
            
            // Set password on user for validation
            $user->setPassword($plainPassword);
            
            // Normalize empty lastName to null for admin/staff (optional field)
            if (($userType === 'admin' || $userType === 'staff') && empty($user->getLastName())) {
                $user->setLastName(null);
            }
            
            // Validate entity with appropriate groups
            $errors = $this->validator->validate($user, null, $validationGroups);
            
            // Check for validation errors
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->render('admin_staff/users/new.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }
            
            // Check if username already exists
            $existingUserByUsername = $usersRepository->findOneBy(['username' => $user->getUsername()]);
            if ($existingUserByUsername) {
                $this->addFlash('error', 'This username is already taken. Please choose another one.');
                return $this->render('admin_staff/users/new.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }

            // Check if email already exists
            $existingUserByEmail = $usersRepository->findOneBy(['email' => $user->getEmail()]);
            if ($existingUserByEmail) {
                $this->addFlash('error', 'An account with this email already exists.');
                return $this->render('admin_staff/users/new.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }

            // Hash the password (already validated above)
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);
            
            // Handle admin vs staff user creation
            
            if ($userType === 'admin') {
                // Admin users are stored in database
                // Only admin users can create admin users
                if (!$isAdmin) {
                    $this->addFlash('error', 'Only administrators can create admin users. You can only create staff users.');
                    return $this->render('admin_staff/users/new.html.twig', [
                        'user' => $user,
                        'form' => $form,
                        'isInMemoryAdmin' => $isAdmin,
                    ]);
                }
                
                $user->setRole('admin');
                $user->setUserType('admin');
            } elseif ($userType === 'staff') {
                // Staff users are stored in database
                $user->setRole('staff');
                $user->setUserType('staff');
            } else {
                $this->addFlash('error', 'Invalid user type selected.');
                return $this->render('admin_staff/users/new.html.twig', [
                    'user' => $user,
                    'form' => $form,
                    'isInMemoryAdmin' => $isAdmin,
                ]);
            }
            
            // Set default values for admin and staff users
            $user->setBio(null); // No bio for admin/staff users
            // Ensure lastName is null (not empty string) for admin/staff if not provided
            if (empty($user->getLastName())) {
                $user->setLastName(null);
            }
            $user->setIsActive(true);
            $user->setVerified(true); // Users created by admin are verified by default
            $user->setCreatedAt(new \DateTimeImmutable());
            $user->setUpdatedAt(new \DateTime());

            // Persist user to database (both admin and staff)
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Manually log the creation (fallback if event subscriber doesn't fire)
            try {
                $this->activityLogService->log(
                    'CREATE',
                    'User',
                    $user->getId(),
                    "Created User: " . ($user->getUsername() ?? 'Unknown')
                );
            } catch (\Exception $e) {
                error_log('Failed to log user creation: ' . $e->getMessage());
            }

            // Notify admins about new user creation
            try {
                $this->notificationService->notifyNewUser($user);
            } catch (\Exception $e) {
                error_log('Failed to send notification for new user: ' . $e->getMessage());
            }

            $this->addFlash('success', 'User created successfully.');
            return $this->redirectToRoute('app_user_index');
        }

        return $this->render('admin_staff/users/new.html.twig', [
            'user' => $user,
            'form' => $form,
            'isInMemoryAdmin' => $isAdmin,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', methods: ['GET'])]
    public function show(Users $user): Response
    {
        // Note: Staff users can access this page but will see a restricted access overlay in the template
        $response = $this->render('admin_staff/users/show.html.twig', [
            'user' => $user,
        ]);
        
        // Prevent caching
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        
        return $response;
    }

    #[Route('/{id}/toggle-status', name: 'app_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Users $user, Request $request): JsonResponse
    {
        // Prevent staff users from toggling user status - only admins can do this
        $currentUser = $this->getUser();
        if ($currentUser instanceof Users && ($currentUser->getRole() === 'staff' || $currentUser->getUserType() === 'staff')) {
            return new JsonResponse(['success' => false, 'message' => 'You do not have permission to change user status. Only administrators can manage users.'], 403);
        }

        // Prevent disabling admin users
        if ($user->getUserType() === 'admin' || $user->getRole() === 'admin') {
            return new JsonResponse(['success' => false, 'message' => 'Cannot change status for admin users.'], 400);
        }

        $user->setIsActive(!$user->isActive());
        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        // Notify admins about user status change
        try {
            $admin = $this->getUser();
            $changedBy = $admin instanceof Users ? $admin->getUsername() : 'System';
            $changeType = $user->isActive() ? 'enabled' : 'disabled';
            $this->notificationService->notifyUserStatusChange($user, $changeType, $changedBy);
        } catch (\Exception $e) {
            // Log error but don't fail status change
            error_log('Failed to send notification for user status change: ' . $e->getMessage());
        }

        $status = $user->isActive() ? 'enabled' : 'disabled';
        return new JsonResponse(['success' => true, 'message' => "User {$status} successfully.", 'isActive' => $user->isActive()]);
    }

    #[Route('/{id}/reset-password', name: 'app_user_reset_password', methods: ['POST'])]
    public function resetPassword(Users $user, Request $request): JsonResponse
    {
        // Prevent staff users from resetting passwords - only admins can do this
        $currentUser = $this->getUser();
        if ($currentUser instanceof Users && ($currentUser->getRole() === 'staff' || $currentUser->getUserType() === 'staff')) {
            return new JsonResponse(['success' => false, 'message' => 'You do not have permission to reset user passwords. Only administrators can manage users.'], 403);
        }

        // Prevent resetting password for admin users
        if ($user->getUserType() === 'admin' || $user->getRole() === 'admin') {
            return new JsonResponse(['success' => false, 'message' => 'Cannot reset password for admin users.'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $newPassword = $data['password'] ?? null;

        if (!$newPassword) {
            return new JsonResponse(['success' => false, 'message' => 'Password is required.'], 400);
        }

        // Hash the new password
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Password reset successfully.']);
    }

    #[Route('/{id}/update-activity', name: 'app_user_update_activity', methods: ['POST'])]
    public function updateActivity(Users $user): JsonResponse
    {
        // Prevent staff users from updating activity - only admins can do this
        $currentUser = $this->getUser();
        if ($currentUser instanceof Users && ($currentUser->getRole() === 'staff' || $currentUser->getUserType() === 'staff')) {
            return new JsonResponse(['success' => false, 'message' => 'You do not have permission to update user activity. Only administrators can manage users.'], 403);
        }

        $user->setLastActivity(new \DateTime());
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Activity updated successfully.']);
    }

    #[Route('/{id}/toggle-verification', name: 'app_user_toggle_verification', methods: ['POST'])]
    public function toggleVerification(Users $user, Request $request): JsonResponse
    {
        // Prevent staff users from toggling verification - only admins can do this
        $currentUser = $this->getUser();
        if ($currentUser instanceof Users && ($currentUser->getRole() === 'staff' || $currentUser->getUserType() === 'staff')) {
            return new JsonResponse(['success' => false, 'message' => 'You do not have permission to change user verification status. Only administrators can manage users.'], 403);
        }

        // Prevent toggling verification for admin and staff users
        if ($user->getUserType() === 'admin' || $user->getUserType() === 'staff') {
            return new JsonResponse(['success' => false, 'message' => 'Cannot change verification status for admin or staff users.'], 400);
        }

        $user->setVerified(!$user->isVerified());
        $user->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        // Notify admins about user verification change
        try {
            $admin = $this->getUser();
            $changedBy = $admin instanceof Users ? $admin->getUsername() : 'System';
            $changeType = $user->isVerified() ? 'verified' : 'unverified';
            $this->notificationService->notifyUserStatusChange($user, $changeType, $changedBy);
        } catch (\Exception $e) {
            // Log error but don't fail verification change
            error_log('Failed to send notification for user verification change: ' . $e->getMessage());
        }

        $status = $user->isVerified() ? 'verified' : 'unverified';
        return new JsonResponse(['success' => true, 'message' => "User {$status} successfully.", 'isVerified' => $user->isVerified()]);
    }

    #[Route('/{id}/promote', name: 'app_user_promote', methods: ['POST'])]
    public function promote(Users $user, Request $request): JsonResponse
    {
        // Only admins can promote users
        $currentUser = $this->getUser();
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'message' => 'You do not have permission to promote users. Only administrators can promote users.'], 403);
        }

        // Only client or designer users can be promoted to staff
        $currentRole = $user->getRole();
        $currentUserType = $user->getUserType();
        
        if (!in_array($currentRole, ['client', 'designer']) || !in_array($currentUserType, ['client', 'designer'])) {
            return new JsonResponse(['success' => false, 'message' => 'Only client or designer users can be promoted to staff.'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $newRole = $data['role'] ?? null;

        // Only allow promotion to staff
        if ($newRole !== 'staff') {
            return new JsonResponse(['success' => false, 'message' => 'Invalid role. Users can only be promoted to staff.'], 400);
        }

        // Update both role and userType to staff
        $user->setRole('staff');
        $user->setUserType('staff');
        $user->setUpdatedAt(new \DateTime());

        // Clear bio when promoting to staff (staff users don't have bios)
        $user->setBio(null);

        $this->entityManager->flush();

        // Notify admins about user promotion
        try {
            $admin = $this->getUser();
            $changedBy = $admin instanceof Users ? $admin->getUsername() : 'System';
            $this->notificationService->notifyUserStatusChange($user, "promoted to staff", $changedBy);
        } catch (\Exception $e) {
            error_log('Failed to send notification for user promotion: ' . $e->getMessage());
        }

        return new JsonResponse([
            'success' => true, 
            'message' => "User successfully promoted to staff.", 
            'newRole' => 'staff',
            'newUserType' => 'staff'
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', methods: ['GET', 'POST'])]
    public function edit(Users $user, Request $request): Response
    {
        // Only admins can edit users
        if (!$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'You do not have permission to edit users. Only administrators can edit users.');
            return $this->redirectToRoute('app_user_index');
        }

        // Prevent editing admin users
        if ($user->getUserType() === 'admin' || $user->getRole() === 'admin') {
            $this->addFlash('error', 'Cannot edit admin users.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        // Prevent editing CLIENT and DESIGNER users
        if ($user->getUserType() === 'client' || $user->getRole() === 'client' || 
            $user->getUserType() === 'designer' || $user->getRole() === 'designer') {
            $this->addFlash('error', 'Cannot edit client or designer users.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        $form = $this->createForm(StaffSettingsType::class, null, [
            'user' => $user,
            'include_role' => true,
            'include_username' => false,
            'include_user_type' => false,
            'include_password' => true
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();

            // Check if email already exists (excluding current user)
            $existingUserByEmail = $this->entityManager->getRepository(Users::class)
                ->findOneBy(['email' => $formData['email']]);
            if ($existingUserByEmail && $existingUserByEmail->getId() !== $user->getId()) {
                $this->addFlash('error', 'This email address is already in use by another account.');
                return $this->render('admin_staff/users/edit.html.twig', [
                    'user' => $user,
                    'form' => $form,
                ]);
            }

            // Update user fields
            $user->setFirstName($formData['firstName']);
            $user->setLastName($formData['lastName'] ?: null);
            $user->setEmail($formData['email']);
            
            // Update password if provided
            if (!empty($formData['password'])) {
                $hashedPassword = $this->passwordHasher->hashPassword($user, $formData['password']);
                $user->setPassword($hashedPassword);
            }
            
            // Update role and userType
            $newRole = $formData['role'];
            
            if ($newRole === 'admin') {
                // Promote user to admin
                $user->setUserType('admin');
                $user->setRole('admin');
                // Clear bio when promoting to admin (admin users don't have bios)
                $user->setBio(null);
            } else {
                // Regular role update
                if (in_array($newRole, ['client', 'designer', 'staff'])) {
                    $user->setRole($newRole);
                    $user->setUserType($newRole);
                    
                    // Clear bio when changing to staff (staff users don't have bios)
                    if ($newRole === 'staff') {
                        $user->setBio(null);
                    }
                }
            }

            $user->setUpdatedAt(new \DateTime());
            
            // Force Doctrine to detect changes
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // Manually log the update (fallback if event subscriber doesn't fire)
            try {
                $this->activityLogService->log(
                    'UPDATE',
                    'User',
                    $user->getId(),
                    "Updated User: " . ($user->getUsername() ?? 'Unknown')
                );
            } catch (\Exception $e) {
                error_log('Failed to log user update: ' . $e->getMessage());
            }

            // Notify admins about user update
            try {
                $admin = $this->getUser();
                $changedBy = $admin instanceof Users ? $admin->getUsername() : 'System';
                $this->notificationService->notifyUserStatusChange($user, 'updated', $changedBy);
            } catch (\Exception $e) {
                error_log('Failed to send notification for user update: ' . $e->getMessage());
            }

            $this->addFlash('success', 'User updated successfully.');
            return $this->redirectToRoute('app_user_show', ['id' => $user->getId()]);
        }

        return $this->render('admin_staff/users/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(Users $user, Request $request): JsonResponse
    {
        // Prevent staff users from deleting users - only admins can delete
        $currentUser = $this->getUser();
        if ($currentUser instanceof Users && ($currentUser->getRole() === 'staff' || $currentUser->getUserType() === 'staff')) {
            return new JsonResponse(['success' => false, 'message' => 'You do not have permission to delete users. Only administrators can delete users.'], 403);
        }

        // Verify admin password
        $data = json_decode($request->getContent(), true);
        $password = $data['password'] ?? null;
        
        if (!$password || empty($password)) {
            return new JsonResponse(['success' => false, 'message' => 'Password is required to confirm deletion.'], 400);
        }
        
        // Verify the admin's password - ensure currentUser is a Users instance
        if (!$currentUser instanceof Users) {
            return new JsonResponse(['success' => false, 'message' => 'Authentication required.'], 401);
        }
        
        if (!$this->passwordHasher->isPasswordValid($currentUser, $password)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid password. Please try again.'], 401);
        }

        // Prevent deleting admin users
        if ($user->getUserType() === 'admin' || $user->getRole() === 'admin') {
            return new JsonResponse(['success' => false, 'message' => 'Cannot delete admin users.'], 400);
        }

        // Prevent deleting yourself (we already know currentUser is a Users instance from line 490)
        if ($currentUser->getId() === $user->getId()) {
            return new JsonResponse(['success' => false, 'message' => 'You cannot delete your own account.'], 400);
        }

        try {
            $username = $user->getUsername();
            $userId = $user->getId();

            // Handle related entities before deletion
            // First, collect all proposals to delete (both as designer and from projects)
            $proposalsToDelete = [];
            
            // Get proposals where user is the designer
            foreach ($user->getProposals() as $proposal) {
                $proposalsToDelete[$proposal->getId()] = $proposal;
            }
            
            // Get proposals from projects owned by this user (as client)
            foreach ($user->getProjects() as $project) {
                foreach ($project->getProposals() as $proposal) {
                    $proposalsToDelete[$proposal->getId()] = $proposal;
                }
            }
            
            // Delete all unique proposals
            foreach ($proposalsToDelete as $proposal) {
                $this->entityManager->remove($proposal);
            }

            // Delete projects owned by this user (client relationship is required, so we must delete)
            foreach ($user->getProjects() as $project) {
                $this->entityManager->remove($project);
            }

            // Flush changes to related entities first
            $this->entityManager->flush();

            // Log deletion before removing (preRemove event will also fire, but this ensures it's logged)
            try {
                $this->activityLogService->log(
                    'DELETE',
                    'User',
                    $userId,
                    "Deleted User: " . $username
                );
            } catch (\Exception $e) {
                error_log('Failed to log user deletion: ' . $e->getMessage());
            }

            // Now delete the user
            $this->entityManager->remove($user);
            $this->entityManager->flush();

            // Notify admins about user deletion (we already know currentUser is a Users instance from line 490)
            try {
                $deletedBy = $currentUser->getUsername();
                $this->notificationService->notifyAllAdmins(
                    'user_deleted',
                    'User Deleted',
                    "User '{$username}' (ID: {$userId}) has been deleted by {$deletedBy}.",
                    'Users',
                    $userId,
                    '/users'
                );
            } catch (\Exception $e) {
                error_log('Failed to send notification for user deletion: ' . $e->getMessage());
            }

            return new JsonResponse(['success' => true, 'message' => "User '{$username}' deleted successfully."]);
        } catch (\Exception $e) {
            error_log('Failed to delete user: ' . $e->getMessage());
            return new JsonResponse(['success' => false, 'message' => 'An error occurred while deleting the user.'], 500);
        }
    }
}
