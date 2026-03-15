<?php

namespace App\Command;

use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'app:cleanup-orphaned-profile-pictures',
    description: 'Clean up profile pictures that reference deleted files'
)]
class CleanupOrphanedProfilePicturesCommand extends Command
{
    private string $uploadsBaseDir;

    public function __construct(
        private UsersRepository $usersRepository,
        private EntityManagerInterface $entityManager,
        ParameterBagInterface $params
    ) {
        parent::__construct();
        $kernelProjectDir = $params->get('kernel.project_dir');
        $this->uploadsBaseDir = $kernelProjectDir . '/public/uploads';
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Cleaning up orphaned profile pictures');

        // Get all users with profile pictures
        $users = $this->usersRepository->findAll();
        $cleaned = 0;

        foreach ($users as $user) {
            $needsUpdate = false;

            // Check if profilePictureFile exists and is active
            if ($user->getProfilePictureFile()) {
                if (!$user->getProfilePictureFile()->isActive()) {
                    // File was deleted, clear the reference
                    $user->setProfilePictureFile(null);
                    $user->setProfilePicture(null);
                    $needsUpdate = true;
                }
            }

            // Check if old string-based profile picture file exists
            $profilePicture = $user->getProfilePicture();
            if ($profilePicture && !$user->getProfilePictureFile()) {
                // Check if file exists on disk
                $filePath = $this->uploadsBaseDir . '/profile_pictures/' . $profilePicture;
                if (!file_exists($filePath)) {
                    // File doesn't exist, clear the reference
                    $user->setProfilePicture(null);
                    $needsUpdate = true;
                }
            }

            if ($needsUpdate) {
                $this->entityManager->persist($user);
                $cleaned++;
                $io->writeln(sprintf('  - Cleared profile picture for user: %s (ID: %d)', $user->getUsername(), $user->getId()));
            }
        }

        if ($cleaned > 0) {
            $this->entityManager->flush();
            $io->success(sprintf('Cleaned up %d user profile picture(s)', $cleaned));
        } else {
            $io->info('No orphaned profile pictures found.');
        }

        return Command::SUCCESS;
    }
}

