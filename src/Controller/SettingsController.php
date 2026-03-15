<?php

namespace App\Controller;

use App\Repository\SettingsRepository;
use App\Service\SecurityConfigService;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Form\SettingsType;
use App\Form\ChangePasswordType;
use App\Form\StaffSettingsType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/settings')]
class SettingsController extends AbstractController
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private EntityManagerInterface $entityManager,
        private SecurityConfigService $securityConfigService,
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    #[Route('/', name: 'app_admin_settings_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $currentUser = $this->getUser();
        $isStaff = $currentUser instanceof Users && ($currentUser->getRole() === 'staff' || $currentUser->getUserType() === 'staff');
        
        // Staff users get a simplified password change form
        if ($isStaff && !$this->isGranted('ROLE_ADMIN')) {
            return $this->handleStaffSettings($request, $currentUser);
        }
        
        // Admin users get full settings
        return $this->handleAdminSettings($request, $currentUser);
    }
    
    private function handleStaffSettings(Request $request, Users $user): Response
    {
        $activeTab = $request->query->get('tab', 'profile');
        
        // Handle form submission based on active tab
        if ($request->isMethod('POST')) {
            $activeTab = $request->request->get('activeTab', 'profile');
            
            if ($activeTab === 'password') {
                return $this->handlePasswordChange($request, $user);
            } elseif ($activeTab === 'profile') {
                return $this->handleProfileUpdate($request, $user);
            }
        }
        
        // Create forms for display
        $profileForm = $this->createForm(StaffSettingsType::class, null, ['user' => $user]);
        $passwordForm = $this->createForm(ChangePasswordType::class, null, ['user' => $user]);
        
        return $this->render('admin_staff/settings/index.html.twig', [
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
            'isStaff' => true,
            'activeTab' => $activeTab,
            'user' => $user,
        ]);
    }
    
    private function handlePasswordChange(Request $request, Users $user): Response
    {
        $form = $this->createForm(ChangePasswordType::class, null, ['user' => $user]);
        $form->handleRequest($request);
        
        // Check if this is an AJAX request
        $isAjax = $request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
        
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $currentPassword = $formData['currentPassword'];
            $newPassword = $formData['newPassword'];
            $confirmPassword = $formData['confirmPassword'];
            
            // Verify current password
            if (!$this->passwordHasher->isPasswordValid($user, $currentPassword)) {
                if ($isAjax) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Current password is incorrect.'
                    ], 400);
                }
                $this->addFlash('error', 'Current password is incorrect.');
                $profileForm = $this->createForm(StaffSettingsType::class, null, ['user' => $user]);
                $passwordForm = $this->createForm(ChangePasswordType::class, null, ['user' => $user]);
                return $this->render('admin_staff/settings/index.html.twig', [
                    'profileForm' => $profileForm,
                    'passwordForm' => $passwordForm,
                    'isStaff' => true,
                    'activeTab' => 'password',
                    'user' => $user,
                ]);
            }
            
            // Verify new passwords match
            if ($newPassword !== $confirmPassword) {
                if ($isAjax) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'New passwords do not match.'
                    ], 400);
                }
                $this->addFlash('error', 'New passwords do not match.');
                $profileForm = $this->createForm(StaffSettingsType::class, null, ['user' => $user]);
                $passwordForm = $this->createForm(ChangePasswordType::class, null, ['user' => $user]);
                return $this->render('admin_staff/settings/index.html.twig', [
                    'profileForm' => $profileForm,
                    'passwordForm' => $passwordForm,
                    'isStaff' => true,
                    'activeTab' => 'password',
                    'user' => $user,
                ]);
            }
            
            // Check if new password is the same as current password
            if ($this->passwordHasher->isPasswordValid($user, $newPassword)) {
                if ($isAjax) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'New password must be different from your current password.'
                    ], 400);
                }
                $this->addFlash('error', 'New password must be different from your current password.');
                $profileForm = $this->createForm(StaffSettingsType::class, null, ['user' => $user]);
                $passwordForm = $this->createForm(ChangePasswordType::class, null, ['user' => $user]);
                return $this->render('admin_staff/settings/index.html.twig', [
                    'profileForm' => $profileForm,
                    'passwordForm' => $passwordForm,
                    'isStaff' => true,
                    'activeTab' => 'password',
                    'user' => $user,
                ]);
            }
            
            // Hash and save new password
            $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);
            $user->setPassword($hashedPassword);
            $user->setUpdatedAt(new \DateTime());
            
            $this->entityManager->flush();
            
            if ($isAjax) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Your password has been changed successfully.'
                ]);
            }
            
            $this->addFlash('success', 'Your password has been changed successfully.');
            return $this->redirectToRoute('app_admin_settings_index', ['tab' => 'password']);
        }
        
        // Handle form validation errors for AJAX
        if ($isAjax && $form->isSubmitted()) {
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }
            return new JsonResponse([
                'success' => false,
                'message' => !empty($errors) ? implode(' ', $errors) : 'Please check your input and try again.'
            ], 400);
        }
        
        $profileForm = $this->createForm(StaffSettingsType::class, null, ['user' => $user]);
        $passwordForm = $form;
        return $this->render('admin_staff/settings/index.html.twig', [
            'profileForm' => $profileForm,
            'passwordForm' => $passwordForm,
            'isStaff' => true,
            'activeTab' => 'password',
            'user' => $user,
        ]);
    }
    
    private function handleProfileUpdate(Request $request, Users $user): Response
    {
        $form = $this->createForm(StaffSettingsType::class, null, ['user' => $user]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            
            // Check if email or username already exists (excluding current user)
            $existingUserByEmail = $this->entityManager->getRepository(Users::class)
                ->findOneBy(['email' => $formData['email']]);
            if ($existingUserByEmail && $existingUserByEmail->getId() !== $user->getId()) {
                $this->addFlash('error', 'This email address is already in use by another account.');
                $passwordForm = $this->createForm(ChangePasswordType::class, null, ['user' => $user]);
                return $this->render('admin_staff/settings/index.html.twig', [
                    'profileForm' => $form,
                    'passwordForm' => $passwordForm,
                    'isStaff' => true,
                    'activeTab' => 'profile',
                    'user' => $user,
                ]);
            }
            
            $existingUserByUsername = $this->entityManager->getRepository(Users::class)
                ->findOneBy(['username' => $formData['username']]);
            if ($existingUserByUsername && $existingUserByUsername->getId() !== $user->getId()) {
                $this->addFlash('error', 'This username is already in use by another account.');
                $passwordForm = $this->createForm(ChangePasswordType::class, null, ['user' => $user]);
                return $this->render('admin_staff/settings/index.html.twig', [
                    'profileForm' => $form,
                    'passwordForm' => $passwordForm,
                    'isStaff' => true,
                    'activeTab' => 'profile',
                    'user' => $user,
                ]);
            }
            
            // Update user profile
            $user->setFirstName($formData['firstName']);
            $user->setLastName($formData['lastName']);
            $user->setEmail($formData['email']);
            $user->setUsername($formData['username']);
            $user->setUpdatedAt(new \DateTime());
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Your profile has been updated successfully.');
            return $this->redirectToRoute('app_admin_settings_index', ['tab' => 'profile']);
        }
        
        $passwordForm = $this->createForm(ChangePasswordType::class, null, ['user' => $user]);
        return $this->render('admin_staff/settings/index.html.twig', [
            'profileForm' => $form,
            'passwordForm' => $passwordForm,
            'isStaff' => true,
            'activeTab' => 'profile',
            'user' => $user,
        ]);
    }
    
    private function handleAdminSettings(Request $request, Users $user): Response
    {
        $activeTab = $request->query->get('tab', 'profile');
        
        // Handle profile form submission
        if ($request->isMethod('POST') && $request->request->get('activeTab') === 'profile') {
            return $this->handleAdminProfileUpdate($request, $user);
        }
        
        // Get all current settings
        $allSettings = $this->settingsRepository->getAllAsArray();
        
        // Get current admin username from security config
        if (!isset($allSettings['admin_username'])) {
            $allSettings['admin_username'] = $this->securityConfigService->getCurrentAdminUsername();
        }
        
        // Create form with current settings
        $form = $this->createForm(SettingsType::class, null, [
            'settings' => $allSettings
        ]);
        
        // Create profile form for admin
        $profileForm = $this->createForm(StaffSettingsType::class, null, ['user' => $user]);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            $currentUser = $this->getUser();
            
            // Get Users entity if current user is a Users instance, otherwise null
            // (InMemoryUser doesn't have a database record)
            $updatedByUser = ($currentUser instanceof Users) ? $currentUser : null;
            
            // Save all settings
            foreach ($formData as $key => $value) {
                // Skip admin_username (removed from form)
                if ($key === 'admin_username') {
                    continue;
                }
                
                // Determine category based on key prefix
                $category = 'general';
                if (str_starts_with($key, 'smtp_') || str_starts_with($key, 'email_')) {
                    $category = 'email';
                } elseif (str_starts_with($key, 'payment_') || str_starts_with($key, 'stripe_') || str_starts_with($key, 'paypal_') || str_starts_with($key, 'platform_')) {
                    $category = 'payment';
                } elseif (str_starts_with($key, 'password_') || str_starts_with($key, 'session_') || str_starts_with($key, 'admin_')) {
                    $category = 'security';
                } elseif (str_starts_with($key, 'feature_')) {
                    $category = 'features';
                } elseif (str_starts_with($key, 'maintenance_')) {
                    $category = 'maintenance';
                }
                
                // Convert boolean values to string
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif ($value === null) {
                    $value = '';
                } else {
                    $value = (string)$value;
                }
                
                $setting = $this->settingsRepository->setSetting($key, $value, $category);
                $setting->setUpdatedBy($updatedByUser);
                $this->entityManager->persist($setting);
            }
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Settings saved successfully.');
            
            return $this->redirectToRoute('app_admin_settings_index');
        }
        
        return $this->render('admin_staff/settings/index.html.twig', [
            'form' => $form,
            'profileForm' => $profileForm,
            'settings' => $allSettings,
            'isStaff' => false,
            'activeTab' => $activeTab,
            'user' => $user,
        ]);
    }
    
    private function handleAdminProfileUpdate(Request $request, Users $user): Response
    {
        $form = $this->createForm(StaffSettingsType::class, null, ['user' => $user]);
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $formData = $form->getData();
            
            // Check if email or username already exists (excluding current user)
            $existingUserByEmail = $this->entityManager->getRepository(Users::class)
                ->findOneBy(['email' => $formData['email']]);
            if ($existingUserByEmail && $existingUserByEmail->getId() !== $user->getId()) {
                $this->addFlash('error', 'This email address is already in use by another account.');
                return $this->redirectToRoute('app_admin_settings_index', ['tab' => 'profile']);
            }
            
            $existingUserByUsername = $this->entityManager->getRepository(Users::class)
                ->findOneBy(['username' => $formData['username']]);
            if ($existingUserByUsername && $existingUserByUsername->getId() !== $user->getId()) {
                $this->addFlash('error', 'This username is already in use by another account.');
                return $this->redirectToRoute('app_admin_settings_index', ['tab' => 'profile']);
            }
            
            // Update user profile
            $user->setFirstName($formData['firstName']);
            $user->setLastName($formData['lastName']);
            $user->setEmail($formData['email']);
            $user->setUsername($formData['username']);
            $user->setUpdatedAt(new \DateTime());
            
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Your profile has been updated successfully.');
            return $this->redirectToRoute('app_admin_settings_index', ['tab' => 'profile']);
        }
        
        // If form is invalid, re-render with errors
        $allSettings = $this->settingsRepository->getAllSettings();
        if ($this->securityConfigService->getCurrentAdminUsername()) {
            $allSettings['admin_username'] = $this->securityConfigService->getCurrentAdminUsername();
        }
        $settingsForm = $this->createForm(SettingsType::class, null, [
            'settings' => $allSettings
        ]);
        
        return $this->render('admin_staff/settings/index.html.twig', [
            'form' => $settingsForm,
            'profileForm' => $form,
            'settings' => $allSettings,
            'isStaff' => false,
            'activeTab' => 'profile',
            'user' => $user,
        ]);
    }
    
    #[Route('/change-admin-password', name: 'app_admin_change_password', methods: ['POST'])]
    public function changeAdminPassword(Request $request, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        // Only allow admins
        if (!$this->isGranted('ROLE_ADMIN')) {
            return new JsonResponse(['success' => false, 'message' => 'Access denied.'], 403);
        }
        
        $data = json_decode($request->getContent(), true);
        $currentPassword = $data['currentPassword'] ?? null;
        $newPassword = $data['newPassword'] ?? null;
        $confirmPassword = $data['confirmPassword'] ?? null;
        
        if (!$currentPassword || !$newPassword || !$confirmPassword) {
            return new JsonResponse(['success' => false, 'message' => 'All fields are required.'], 400);
        }
        
        // Verify new passwords match
        if ($newPassword !== $confirmPassword) {
            return new JsonResponse(['success' => false, 'message' => 'New passwords do not match.'], 400);
        }
        
        // No password restrictions for admin (in-memory admin user)
        
        // Get current user
        $currentUser = $this->getUser();
        
        if (!$currentUser) {
            $this->addFlash('error', 'User not authenticated.');
            return new JsonResponse([
                'success' => false,
                'message' => 'User not authenticated.',
                'redirect' => $this->generateUrl('app_admin_settings_index', ['tab' => 'password'])
            ], 401);
        }
        
        // Check if current user is a database user (Users entity)
        if ($currentUser instanceof Users) {
            // CRITICAL: Verify current password BEFORE any updates
            // Store original password hash for verification
            $originalPasswordHash = $currentUser->getPassword();
            $isPasswordValid = $passwordHasher->isPasswordValid($currentUser, $currentPassword);
            
            // Double-check: Ensure password verification actually failed
            if (!$isPasswordValid) {
                // Log the failed attempt for security
                error_log('Password change attempt failed for user ID: ' . $currentUser->getId() . ' - Invalid current password');
                
                // Don't set flash message for AJAX requests - let JavaScript handle it
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Current password is incorrect.'
                ], 400);
            }
            
            // Check if new password is the same as current password
            if ($passwordHasher->isPasswordValid($currentUser, $newPassword)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'New password must be different from your current password.'
                ], 400);
            }
            
            // Additional safety check: Verify the password hash hasn't changed (entity wasn't modified)
            if ($currentUser->getPassword() !== $originalPasswordHash) {
                error_log('SECURITY WARNING: User password was modified before verification! User ID: ' . $currentUser->getId());
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Security error: Password verification failed.'
                ], 400);
            }
            
            // Only update password if verification passed (this code should never execute if password is wrong)
            $hashedPassword = $passwordHasher->hashPassword($currentUser, $newPassword);
            $currentUser->setPassword($hashedPassword);
            $currentUser->setUpdatedAt(new \DateTime());
            
            try {
                $this->entityManager->flush();
                // Return success message (no redirect - JavaScript will show inline message)
                return new JsonResponse([
                    'success' => true,
                    'message' => 'Your password has been changed successfully.'
                ]);
            } catch (\Exception $e) {
                error_log('Error changing admin password: ' . $e->getMessage());
                return new JsonResponse([
                    'success' => false,
                    'message' => 'An error occurred while changing the password.'
                ], 500);
            }
        } else {
            // Current user is in-memory admin (from security.yaml), verify against security.yaml
            try {
                $yamlContent = file_get_contents($this->securityConfigService->getSecurityConfigPath());
                $config = \Symfony\Component\Yaml\Yaml::parse($yamlContent);
                
                $currentPasswordHash = null;
                if (isset($config['security']['providers']['admin_provider']['memory']['users'])) {
                    $users = $config['security']['providers']['admin_provider']['memory']['users'];
                    foreach ($users as $key => $value) {
                        if (isset($value['roles']) && in_array('ROLE_ADMIN', $value['roles']) && isset($value['password'])) {
                            $currentPasswordHash = $value['password'];
                            break;
                        }
                    }
                }
                
                if (!$currentPasswordHash) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Unable to verify current password. Admin user not found in security configuration.'
                    ], 400);
                }
                
                // Verify current password
                if (!password_verify($currentPassword, $currentPasswordHash)) {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Current password is incorrect.'
                    ], 400);
                }
                
                // Get current admin username
                $adminUsername = $this->securityConfigService->getCurrentAdminUsername();
                if (!$adminUsername) {
                    $adminUsername = 'admin';
                }
                
                // Update password in security.yaml
                if ($this->securityConfigService->updateAdminCredentials($adminUsername, $newPassword)) {
                    // Return success message (no redirect - JavaScript will show inline message)
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'Your password has been changed successfully.'
                    ]);
                } else {
                    return new JsonResponse([
                        'success' => false,
                        'message' => 'Failed to update password. Please check file permissions.'
                    ], 500);
                }
            } catch (\Exception $e) {
                error_log('Error changing admin password: ' . $e->getMessage());
                return new JsonResponse([
                    'success' => false,
                    'message' => 'An error occurred while changing the password.'
                ], 500);
            }
        }
    }
}

