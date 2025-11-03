<?php

namespace App\Controller;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DesginerProfileController extends AbstractController
{
    #[Route('/designer/profile', name: 'app_designer_profile')]
    public function index(): Response
    {
        return $this->render('designer/desginer_profile/index.html.twig', [
            'controller_name' => 'DesginerProfileController',
        ]);
    }

    #[Route('/designer/profile/upload-photo', name: 'app_designer_profile_upload_photo', methods: ['POST'])]
    public function uploadPhoto(
        Request $request,
        EntityManagerInterface $entityManager,
        UsersRepository $usersRepository
    ): JsonResponse {
        // Ensure user is authenticated
        $user = $this->getUser();
        if (!$user) {
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

        // Delete old profile picture if it exists
        $oldPicture = $user->getProfilePicture();
        if ($oldPicture && file_exists($uploadDir . '/' . $oldPicture)) {
            @unlink($uploadDir . '/' . $oldPicture);
        }

        // Update user entity
        $user->setProfilePicture($filename);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Profile picture uploaded successfully.',
            'filename' => $filename
        ], Response::HTTP_OK);
    }

    #[Route('/designer/profile/update-bio', name: 'app_designer_profile_update_bio', methods: ['POST'])]
    public function updateBio(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Ensure user is authenticated
        $user = $this->getUser();
        if (!$user) {
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
