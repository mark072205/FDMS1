<?php

namespace App\Command;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-users',
    description: 'Remove all users from the database',
)]
class CleanupUsersCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force deletion without confirmation')
            ->setHelp('This command will remove all users from the database. Use --force to skip confirmation.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Check if force option is used
        $force = $input->getOption('force');
        
        if (!$force) {
            $io->warning('This will permanently delete ALL users from the database!');
            $io->note('This action cannot be undone.');
            
            if (!$io->confirm('Are you sure you want to continue?', false)) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        try {
            // Get count of users before deletion
            $userCount = $this->entityManager->getRepository(Users::class)->count([]);
            
            if ($userCount === 0) {
                $io->info('No users found in the database.');
                return Command::SUCCESS;
            }

            $io->info("Found {$userCount} user(s) to delete.");

            // Delete all users
            $this->entityManager->createQuery('DELETE FROM App\Entity\Users u')->execute();
            
            $io->success("Successfully deleted {$userCount} user(s) from the database.");
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('An error occurred while deleting users: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
