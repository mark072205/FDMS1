<?php

namespace App\Controller;

use App\Entity\File;
use App\Entity\Users;
use App\Repository\FileRepository;
use App\Repository\UsersRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ClientProfileController extends AbstractController
{
    #[Route('/client/profile', name: 'app_client_profile')]
    public function index(): Response
    {
        return $this->render('client/client_profile/index.html.twig', [
            'controller_name' => 'ClientProfileController',
        ]);
    }

    #[Route('/client/profile/upload-photo', name: 'app_client_profile_upload_photo', methods: ['POST'])]
    public function uploadPhoto(
        Request $request,
        EntityManagerInterface $entityManager,
        UsersRepository $usersRepository,
        FileRepository $fileRepository,
        NotificationService $notificationService
    ): JsonResponse {
        // Ensure user is authenticated and is a Users entity
        $user = $this->getUser();
        if (!$user || !($user instanceof Users)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You must be logged in to upload a profile picture.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Get the image data from the request
        $imageDataUrl = $request->request->get('image');
        
        if (!$imageDataUrl) {
            return new JsonResponse([
                'success' => false,
                'message' => 'No image data provided.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Decode base64 image data
        $type = 'png'; // default
        if (preg_match('/^data:image\/(\w+);base64,/', $imageDataUrl, $matches)) {
            $type = strtolower($matches[1]); // jpg, png, gif, etc.
            // Normalize jpeg to jpg
            if ($type === 'jpeg') {
                $type = 'jpg';
            }
            
            if (!in_array($type, ['jpg', 'png', 'gif', 'webp'])) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Invalid image type. Only JPG, PNG, GIF, and WEBP are allowed.'
                ], Response::HTTP_BAD_REQUEST);
            }
            
            // Remove data URL prefix
            $imageDataUrl = substr($imageDataUrl, strpos($imageDataUrl, ',') + 1);
        }

        $imageData = base64_decode($imageDataUrl, true);
        if ($imageData === false) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to decode image data.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Ensure uploads directory exists
        $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/profile_pictures';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $filename = 'profile_' . $user->getId() . '_' . time() . '.' . $type;
        $filepath = $uploadDir . '/' . $filename;

        // Save the image file
        if (file_put_contents($filepath, $imageData) === false) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to save image file.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        // Handle old profile picture
        $oldPicture = $user->getProfilePicture();
        $oldPictureFile = $user->getProfilePictureFile();
        
        // Deactivate old profile picture file entity if it exists
        if ($oldPictureFile) {
            $oldPictureFile->setIsActive(false);
            $oldPictureFile->decrementUsageCount();
        }
        
        // Delete old profile picture file from filesystem if it exists
        // Handle both filename-only format and full path format
        if ($oldPicture) {
            // Normalize to just filename
            $oldFilename = basename($oldPicture);
            $oldFilename = str_replace('profile_pictures/', '', $oldFilename);
            $oldFilename = str_replace('profile_pictures\\', '', $oldFilename);
            
            $oldFilePath = $uploadDir . '/' . $oldFilename;
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath);
            }
        }

        // Create File entity for the new profile picture
        $relativePath = 'profile_pictures/' . $filename;
        $fileSize = filesize($filepath);
        $mimeType = mime_content_type($filepath) ?: 'image/' . $type;
        
        // Check if file already exists in database (shouldn't happen, but just in case)
        $existingFile = $fileRepository->findByPath($relativePath);
        if ($existingFile) {
            $fileEntity = $existingFile;
            $fileEntity->setIsActive(true);
        } else {
            $fileEntity = new File();
            $fileEntity->setFilename($filename);
            $fileEntity->setPath($relativePath);
            $fileEntity->setSize($fileSize);
            $fileEntity->setMimeType($mimeType);
            $fileEntity->setType('image');
            $fileEntity->setExtension($type);
            $fileEntity->setUploadedBy($user);
            $fileEntity->setUploadedAt(new \DateTimeImmutable());
            $fileEntity->setIsActive(true);
            $entityManager->persist($fileEntity);
        }
        
        // Link file to user and increment usage count
        $fileEntity->incrementUsageCount();
        $user->setProfilePicture($filename);
        $user->setProfilePictureFile($fileEntity);
        
        $entityManager->flush();

        // Notify admins about profile picture change
        try {
            $notificationService->notifyProfilePictureChange($user);
        } catch (\Exception $e) {
            // Log error but don't fail profile picture upload
            error_log('Failed to send notification for profile picture change: ' . $e->getMessage());
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Profile picture uploaded successfully.',
            'filename' => $filename
        ], Response::HTTP_OK);
    }

    #[Route('/client/profile/update-bio', name: 'app_client_profile_update_bio', methods: ['POST'])]
    public function updateBio(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Ensure user is authenticated and is a Users entity
        $user = $this->getUser();
        if (!$user || !($user instanceof Users)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'You must be logged in to update your bio.'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $bio = $data['bio'] ?? null;

        // Bio can be empty/null, so we don't need to check if it's provided
        $user->setBio($bio ? trim($bio) : null);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Bio updated successfully.',
            'bio' => $user->getBio()
        ], Response::HTTP_OK);
    }
}
