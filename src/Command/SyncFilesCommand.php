<?php

namespace App\Command;

use App\Entity\File;
use App\Repository\FileRepository;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:sync-files',
    description: 'Sync files from filesystem to database',
)]
class SyncFilesCommand extends Command
{
    private string $uploadsBaseDir;

    public function __construct(
        ParameterBagInterface $params,
        private FileRepository $fileRepository,
        private UsersRepository $usersRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
        $kernelProjectDir = $params->get('kernel.project_dir');
        $this->uploadsBaseDir = $kernelProjectDir . '/public/uploads';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Syncing files from filesystem to database');

        if (!is_dir($this->uploadsBaseDir)) {
            $io->error('Uploads directory does not exist: ' . $this->uploadsBaseDir);
            return Command::FAILURE;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->uploadsBaseDir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $synced = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $relativePath = str_replace($this->uploadsBaseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
                $relativePath = str_replace('\\', '/', $relativePath);

                // Check if file already exists in database
                $existingFile = $this->fileRepository->findByPath($relativePath);
                if ($existingFile) {
                    $skipped++;
                    continue;
                }

                try {
                    $fileEntity = $this->createFileEntity($file->getPathname(), $relativePath);
                    
                    // If this is a profile picture, try to link it to the user
                    if (strpos($relativePath, 'profile_pictures/') === 0) {
                        $filename = basename($relativePath);
                        $user = $this->usersRepository->createQueryBuilder('u')
                            ->where('u.profilePicture = :filename')
                            ->setParameter('filename', $filename)
                            ->getQuery()
                            ->getOneOrNullResult();
                        
                        if ($user) {
                            $fileEntity->setUploadedBy($user);
                            $fileEntity->incrementUsageCount();
                            $user->setProfilePictureFile($fileEntity);
                        }
                    }
                    
                    $this->entityManager->persist($fileEntity);
                    $synced++;
                } catch (\Exception $e) {
                    $io->warning('Error syncing file: ' . $relativePath . ' - ' . $e->getMessage());
                    $errors++;
                }
            }
        }

        $this->entityManager->flush();

        // Update usage counts for profile pictures
        $this->updateUsageCounts($io);

        $io->success(sprintf(
            'Sync completed: %d files synced, %d skipped, %d errors',
            $synced,
            $skipped,
            $errors
        ));

        return Command::SUCCESS;
    }

    private function createFileEntity(string $filePath, string $relativePath): File
    {
        $fileInfo = new \SplFileInfo($filePath);
        $extension = strtolower($fileInfo->getExtension());

        // Determine file type
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

        $file = new File();
        $file->setFilename($fileInfo->getFilename());
        $file->setPath($relativePath);
        $file->setSize($fileInfo->getSize());
        $file->setMimeType(mime_content_type($filePath) ?: 'application/octet-stream');
        $file->setType($type);
        $file->setExtension($extension);
        $file->setUploadedAt(\DateTimeImmutable::createFromFormat('U', $fileInfo->getMTime()));

        return $file;
    }

    private function updateUsageCounts(SymfonyStyle $io): void
    {
        $io->section('Updating usage counts for profile pictures');

        // Get all users with profile pictures
        $users = $this->usersRepository->createQueryBuilder('u')
            ->where('u.profilePicture IS NOT NULL')
            ->getQuery()
            ->getResult();

        $updated = 0;
        $notFound = 0;
        foreach ($users as $user) {
            $profilePictureFilename = $user->getProfilePicture();
            if ($profilePictureFilename) {
                // Profile pictures are stored with just the filename, but File entity uses full relative path
                // Try both the filename and the full path
                $fullPath = 'profile_pictures/' . $profilePictureFilename;
                
                // First try the full path (how it should be stored in File entity)
                $file = $this->fileRepository->findByPath($fullPath);
                
                // If not found, try just the filename (in case it was synced differently)
                if (!$file) {
                    $file = $this->fileRepository->findByPath($profilePictureFilename);
                }
                
                // If still not found, try to find by filename in the path
                if (!$file) {
                    $file = $this->fileRepository->createQueryBuilder('f')
                        ->where('f.path LIKE :path')
                        ->orWhere('f.filename = :filename')
                        ->setParameter('path', '%' . $profilePictureFilename)
                        ->setParameter('filename', $profilePictureFilename)
                        ->getQuery()
                        ->getOneOrNullResult();
                }
                
                if ($file) {
                    // Link the file to the user
                    $user->setProfilePictureFile($file);
                    $file->incrementUsageCount();
                    $updated++;
                } else {
                    $notFound++;
                    $io->warning(sprintf('Profile picture not found in files table for user %s: %s', $user->getUsername(), $profilePictureFilename));
                }
            }
        }

        $this->entityManager->flush();
        $io->info(sprintf('Updated %d profile picture relationships', $updated));
        if ($notFound > 0) {
            $io->warning(sprintf('%d profile pictures not found in files table - they may need to be synced first', $notFound));
        }
    }
}


