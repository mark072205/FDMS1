<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use App\Repository\UsersRepository;
use App\Repository\FileRepository;
use App\Entity\File;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Connection;

#[Route('/files')]
final class FilesController extends AbstractController
{
    private string $uploadsBaseDir;

    public function __construct(
        ParameterBagInterface $params,
        private UsersRepository $usersRepository,
        private FileRepository $fileRepository,
        private EntityManagerInterface $entityManager,
        private Connection $connection
    ) {
        $kernelProjectDir = $params->get('kernel.project_dir');
        $this->uploadsBaseDir = $kernelProjectDir . '/public/uploads';
    }

    #[Route('/', name: 'app_admin_file_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        // Get filter parameters
        $typeFilter = $request->query->get('type', '');
        $dateFrom = $request->query->get('date_from', '');
        $dateTo = $request->query->get('date_to', '');
        $searchQuery = $request->query->get('search', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 20;

        // Build query using repository
        $qb = $this->fileRepository->createQueryBuilder('f')
            ->where('f.isActive = :active')
            ->setParameter('active', true);

        // Apply filters
        if ($typeFilter) {
            $qb->andWhere('f.type = :type')
               ->setParameter('type', $typeFilter);
        }

        if ($dateFrom) {
            try {
                $dateFromObj = new \DateTimeImmutable($dateFrom);
                $qb->andWhere('f.uploadedAt >= :dateFrom')
                   ->setParameter('dateFrom', $dateFromObj);
            } catch (\Exception $e) {
                // Invalid date, ignore
            }
        }

        if ($dateTo) {
            try {
                $dateToObj = new \DateTimeImmutable($dateTo . ' 23:59:59');
                $qb->andWhere('f.uploadedAt <= :dateTo')
                   ->setParameter('dateTo', $dateToObj);
            } catch (\Exception $e) {
                // Invalid date, ignore
            }
        }

        if ($searchQuery) {
            $qb->andWhere('f.filename LIKE :search OR f.path LIKE :search')
               ->setParameter('search', '%' . $searchQuery . '%');
        }

        // Get total count
        $totalFiles = (int) (clone $qb)->select('COUNT(f.id)')->getQuery()->getSingleScalarResult();

        // Apply sorting and pagination
        $qb->orderBy('f.uploadedAt', 'DESC')
           ->setFirstResult(($page - 1) * $perPage)
           ->setMaxResults($perPage);

        $files = $qb->getQuery()->getResult();

        // Convert File entities to array format for template compatibility with references
        $filesArray = array_map(function($file) {
            return $this->checkFileReferencesForEntity($file);
        }, $files);

        // Calculate pagination info
        $totalPages = (int) ceil($totalFiles / $perPage);

        // Get unique file types for filter
        $fileTypes = $this->fileRepository->createQueryBuilder('f')
            ->select('DISTINCT f.type')
            ->where('f.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('f.type', 'ASC')
            ->getQuery()
            ->getResult();
        $fileTypes = array_column($fileTypes, 'type');

        // Calculate statistics
        $stats = $this->fileRepository->getStatistics();
        $stats['totalSizeFormatted'] = $this->formatFileSize($stats['totalSize'] ?? 0);

        return $this->render('admin_staff/files/index.html.twig', [
            'files' => $filesArray,
            'fileTypes' => $fileTypes,
            'typeFilter' => $typeFilter,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'searchQuery' => $searchQuery,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalFiles' => $totalFiles,
            'perPage' => $perPage,
            'stats' => $stats,
        ]);
    }

    #[Route('/{path}', name: 'app_admin_file_show', requirements: ['path' => '.+'], methods: ['GET'])]
    public function show(string $path): Response
    {
        // Decode the path
        $decodedPath = urldecode($path);
        
        // Try to find file in database first
        $file = $this->fileRepository->findByPath($decodedPath);
        
        if (!$file) {
            // Fallback to filesystem for backward compatibility
            $filePath = $this->uploadsBaseDir . '/' . $decodedPath;
            $realPath = realpath($filePath);
            $realBaseDir = realpath($this->uploadsBaseDir);
            
            if (!$realPath || strpos($realPath, $realBaseDir) !== 0 || !file_exists($realPath) || !is_file($realPath)) {
                throw $this->createNotFoundException('File not found.');
            }

            $fileInfo = $this->getFileInfo($realPath, $decodedPath);
            $filesWithRefs = $this->checkFileReferences([$fileInfo]);
            $fileInfo = $filesWithRefs[0] ?? $fileInfo;

            return $this->render('admin_staff/files/show.html.twig', [
                'file' => $fileInfo,
                'filePath' => $decodedPath,
            ]);
        }

        // Convert File entity to array format
        $fileInfo = $this->fileToArray($file);
        $fileInfo = $this->checkFileReferencesForEntity($file);

        return $this->render('admin_staff/files/show.html.twig', [
            'file' => $fileInfo,
            'filePath' => $decodedPath,
        ]);
    }

    #[Route('/{path}/delete', name: 'app_admin_file_delete', requirements: ['path' => '.+'], methods: ['POST'])]
    public function delete(Request $request, string $path): JsonResponse
    {
        // Decode the path
        $decodedPath = urldecode($path);
        
        // Verify CSRF token
        if (!$this->isCsrfTokenValid('delete_file', $request->request->get('_token'))) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Invalid security token.'
            ], Response::HTTP_FORBIDDEN);
        }

        // Try to find file in database
        $file = $this->fileRepository->findByPath($decodedPath);
        
        $filePath = $this->uploadsBaseDir . '/' . $decodedPath;
        $realPath = realpath($filePath);
        $realBaseDir = realpath($this->uploadsBaseDir);
        
        // Security check: ensure file is within uploads directory
        if (!$realPath || strpos($realPath, $realBaseDir) !== 0) {
            return new JsonResponse([
                'success' => false,
                'message' => 'File not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        if (!file_exists($realPath) || !is_file($realPath)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'File not found.'
            ], Response::HTTP_NOT_FOUND);
        }

        // Before deleting, check if this file is used as a profile picture and reset it
        $usersUpdated = 0;
        
        if ($file) {
            // Find users with this file as profilePictureFile (new relationship)
            $usersWithFile = $this->usersRepository->createQueryBuilder('u')
                ->where('u.profilePictureFile = :file')
                ->setParameter('file', $file)
                ->getQuery()
                ->getResult();
            
            foreach ($usersWithFile as $user) {
                $user->setProfilePictureFile(null);
                $user->setProfilePicture(null);
                // Mark entity as changed
                $this->entityManager->persist($user);
                $usersUpdated++;
            }
        }
        
        // Also check for users with the old string-based profilePicture
        // Check both the full path and just the filename
        $filename = basename($decodedPath);
        // Also check if the path contains the filename (for cases where path is "profile_pictures/filename.png" but user has just "filename.png")
        // The user's profilePicture field might have just the filename, while the file path includes the directory
        $usersWithString = $this->usersRepository->createQueryBuilder('u')
            ->where('u.profilePicture = :path OR u.profilePicture = :filename OR u.profilePicture LIKE :filenamePattern OR u.profilePicture = :pathWithoutDir')
            ->setParameter('path', $decodedPath)
            ->setParameter('filename', $filename)
            ->setParameter('filenamePattern', '%' . $filename)
            ->setParameter('pathWithoutDir', str_replace('profile_pictures/', '', $decodedPath))
            ->getQuery()
            ->getResult();
        
        foreach ($usersWithString as $user) {
            $user->setProfilePicture(null);
            $user->setProfilePictureFile(null);
            // Mark entity as changed
            $this->entityManager->persist($user);
            $usersUpdated++;
        }
        
        // Try to delete the physical file
        $fileDeleted = false;
        if (file_exists($realPath) && is_file($realPath)) {
            $fileDeleted = @unlink($realPath);
        } else {
            // File doesn't exist on disk, but we should still delete from database
            $fileDeleted = true;
        }
        
        // Delete the database entity regardless of physical file deletion status
        // (in case file was already deleted but record remains)
        $fileToDelete = $file;
        
        if (!$fileToDelete) {
            // If file not found by path, try to find it by filename or other means
            // This handles cases where path might not match exactly
            $filename = basename($decodedPath);
            $fileToDelete = $this->fileRepository->createQueryBuilder('f')
                ->where('f.filename = :filename')
                ->setParameter('filename', $filename)
                ->getQuery()
                ->getOneOrNullResult();
        }
        
        if ($fileToDelete) {
            // Remove the file entity
            $this->entityManager->remove($fileToDelete);
        }
        
        // Save all changes (user updates and file deletion)
        try {
            $this->entityManager->flush();
            
            // Double-check: if file entity still exists, try direct SQL deletion
            if ($fileToDelete) {
                $fileId = $fileToDelete->getId();
                // Refresh to check if it was actually deleted
                $this->entityManager->clear();
                $stillExists = $this->fileRepository->find($fileId);
                if ($stillExists) {
                    // Use direct SQL as fallback
                    $this->connection->executeStatement(
                        'DELETE FROM files WHERE id = ?',
                        [$fileId]
                    );
                }
            }
            
            $message = 'File deleted successfully.';
            if ($usersUpdated > 0) {
                $message .= ' ' . $usersUpdated . ' user profile picture(s) reset to default.';
            }
            
            if (!$fileDeleted && file_exists($realPath)) {
                $message .= ' Note: Physical file deletion failed, but database record removed.';
            }
            
            return new JsonResponse([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log('File deletion error: ' . $e->getMessage());
            error_log('File path: ' . $decodedPath);
            error_log('File entity found: ' . ($file ? 'Yes (ID: ' . $file->getId() . ')' : 'No'));
            
            // Try direct SQL deletion as last resort
            if ($fileToDelete) {
                try {
                    $this->connection->executeStatement(
                        'DELETE FROM files WHERE id = ?',
                        [$fileToDelete->getId()]
                    );
                    return new JsonResponse([
                        'success' => true,
                        'message' => 'File deleted from database using direct SQL.'
                    ]);
                } catch (\Exception $sqlError) {
                    error_log('Direct SQL deletion also failed: ' . $sqlError->getMessage());
                }
            }
            
            return new JsonResponse([
                'success' => false,
                'message' => 'Failed to delete file record: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function scanUploadsDirectory(): array
    {
        $files = [];
        
        if (!is_dir($this->uploadsBaseDir)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->uploadsBaseDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($this->uploadsBaseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath); // Normalize path separators
                
                $files[] = $this->getFileInfo($file->getPathname(), $relativePath);
            }
        }

        return $files;
    }

    private function getFileInfo(string $filePath, string $relativePath): array
    {
        $fileInfo = new \SplFileInfo($filePath);
        $extension = strtolower($fileInfo->getExtension());
        
        // Determine file type category
        $type = 'other';
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'])) {
            $type = 'image';
        } elseif (in_array($extension, ['pdf', 'doc', 'docx', 'txt', 'rtf'])) {
            $type = 'document';
        } elseif (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'])) {
            $type = 'archive';
        } elseif (in_array($extension, ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm'])) {
            $type = 'video';
        } elseif (in_array($extension, ['mp3', 'wav', 'ogg', 'flac', 'aac'])) {
            $type = 'audio';
        }

        return [
            'name' => $fileInfo->getFilename(),
            'path' => $relativePath,
            'fullPath' => $filePath,
            'size' => $fileInfo->getSize(),
            'sizeFormatted' => $this->formatFileSize($fileInfo->getSize()),
            'type' => $type,
            'extension' => $extension,
            'modified' => \DateTime::createFromFormat('U', $fileInfo->getMTime()),
            'mimeType' => mime_content_type($filePath) ?: 'application/octet-stream',
            'inUse' => false,
            'references' => [],
            'lastUsed' => null,
        ];
    }

    private function checkFileReferences(array $files): array
    {
        // Get all users with profile pictures
        $usersWithPictures = $this->usersRepository->createQueryBuilder('u')
            ->where('u.profilePicture IS NOT NULL')
            ->getQuery()
            ->getResult();

        // Create a map of filename => user info
        $profilePictureMap = [];
        foreach ($usersWithPictures as $user) {
            $filename = $user->getProfilePicture();
            if ($filename) {
                $lastUsed = $user->getUpdatedAt();
                if (!$lastUsed) {
                    $createdAt = $user->getCreatedAt();
                    $lastUsed = $createdAt ? \DateTime::createFromImmutable($createdAt) : null;
                }
                
                $profilePictureMap[$filename] = [
                    'type' => 'profile_picture',
                    'user' => $user,
                    'lastUsed' => $lastUsed,
                ];
            }
        }

        // Check each file for references
        foreach ($files as &$file) {
            $filename = $file['name'];
            $references = [];

            // Check if it's a profile picture
            if (isset($profilePictureMap[$filename])) {
                $ref = $profilePictureMap[$filename];
                $user = $ref['user'];
                $references[] = [
                    'type' => 'Profile Picture',
                    'entity' => 'User',
                    'entityId' => $user->getId(),
                    'entityName' => $user->getUsername(),
                    'lastUsed' => $ref['lastUsed'],
                ];
                $file['inUse'] = true;
                $file['lastUsed'] = $ref['lastUsed'];
            }

            // Check if file is in profile_pictures directory (even if not referenced)
            if (strpos($file['path'], 'profile_pictures/') === 0) {
                // It's a profile picture file, check if it's referenced
                if (!isset($profilePictureMap[$filename])) {
                    // Orphaned profile picture
                    $file['inUse'] = false;
                }
            }

            $file['references'] = $references;
        }

        return $files;
    }

    private function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    private function calculateStatistics(array $files): array
    {
        $stats = [
            'total' => count($files),
            'totalSize' => 0,
            'byType' => [],
            'byCategory' => [
                'image' => 0,
                'document' => 0,
                'archive' => 0,
                'video' => 0,
                'audio' => 0,
                'other' => 0,
            ],
        ];

        foreach ($files as $file) {
            $stats['totalSize'] += $file['size'];
            $stats['byCategory'][$file['type']] = ($stats['byCategory'][$file['type']] ?? 0) + 1;
            
            $ext = $file['extension'];
            $stats['byType'][$ext] = ($stats['byType'][$ext] ?? 0) + 1;
        }

        $stats['totalSizeFormatted'] = $this->formatFileSize($stats['totalSize']);
        arsort($stats['byType']);

        return $stats;
    }

    /**
     * Convert File entity to array format for template compatibility
     */
    private function fileToArray(File $file): array
    {
        $filePath = $this->uploadsBaseDir . '/' . $file->getPath();
        $modified = file_exists($filePath) ? \DateTime::createFromFormat('U', filemtime($filePath)) : $file->getUploadedAt();
        
        return [
            'id' => $file->getId(),
            'name' => $file->getFilename(),
            'path' => $file->getPath(),
            'fullPath' => $filePath,
            'size' => $file->getSize(),
            'sizeFormatted' => $file->getSizeFormatted(),
            'type' => $file->getType(),
            'extension' => $file->getExtension(),
            'modified' => $modified ?: $file->getUploadedAt(),
            'mimeType' => $file->getMimeType(),
            'inUse' => $file->getUsageCount() > 0,
            'references' => [],
            'lastUsed' => $file->getUpdatedAt(),
            'usageCount' => $file->getUsageCount(),
        ];
    }

    /**
     * Check file references for File entity
     */
    private function checkFileReferencesForEntity(File $file): array
    {
        $fileArray = $this->fileToArray($file);
        $references = [];
        $seenUserIds = []; // Track user IDs to prevent duplicates

        // Check if it's used as a profile picture (new File entity relationship)
        $usersWithFile = $this->usersRepository->createQueryBuilder('u')
            ->where('u.profilePictureFile = :file')
            ->setParameter('file', $file)
            ->getQuery()
            ->getResult();

        foreach ($usersWithFile as $user) {
            $userId = $user->getId();
            if (!in_array($userId, $seenUserIds)) {
            $references[] = [
                'type' => 'Profile Picture',
                'entity' => 'User',
                    'entityId' => $userId,
                'entityName' => $user->getUsername(),
                'lastUsed' => $user->getUpdatedAt() ?: $user->getCreatedAt(),
            ];
                $seenUserIds[] = $userId;
            }
        }

        // Also check old string-based profile pictures
        // Check both the full path and just the filename (for backward compatibility)
        $filePath = $file->getPath();
        $filename = basename($filePath);
        $filename = str_replace('profile_pictures/', '', $filename);
        $filename = str_replace('profile_pictures\\', '', $filename);
        
        $usersWithString = $this->usersRepository->createQueryBuilder('u')
            ->where('u.profilePicture = :path OR u.profilePicture = :filename')
            ->setParameter('path', $filePath)
            ->setParameter('filename', $filename)
            ->getQuery()
            ->getResult();

        foreach ($usersWithString as $user) {
            $userId = $user->getId();
            // Only add if we haven't seen this user already (from the File entity check above)
            if (!in_array($userId, $seenUserIds)) {
            $references[] = [
                'type' => 'Profile Picture',
                'entity' => 'User',
                    'entityId' => $userId,
                'entityName' => $user->getUsername(),
                'lastUsed' => $user->getUpdatedAt() ?: $user->getCreatedAt(),
            ];
                $seenUserIds[] = $userId;
            }
        }

        $fileArray['references'] = $references;
        $fileArray['inUse'] = count($references) > 0 || $file->getUsageCount() > 0;
        
        if (count($references) > 0) {
            $lastUsed = null;
            foreach ($references as $ref) {
                if ($ref['lastUsed'] && (!$lastUsed || $ref['lastUsed'] > $lastUsed)) {
                    $lastUsed = $ref['lastUsed'];
                }
            }
            $fileArray['lastUsed'] = $lastUsed;
        }

        return $fileArray;
    }
}

