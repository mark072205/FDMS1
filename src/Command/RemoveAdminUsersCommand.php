<?php

namespace App\Command;

use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:remove-admin-users',
    description: 'Remove all admin users from the database (admin users should only exist in-memory)'
)]
class RemoveAdminUsersCommand extends Command
{
    public function __construct(
        private UsersRepository $usersRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Find all admin users (including system admin user with ID 0 or username '__system_admin__')
        $adminUsers = $this->usersRepository->createQueryBuilder('u')
            ->where('u.role = :adminRole OR u.userType = :adminType OR u.id = :systemAdminId OR u.username = :systemAdminUsername')
            ->setParameter('adminRole', 'admin')
            ->setParameter('adminType', 'admin')
            ->setParameter('systemAdminId', 0)
            ->setParameter('systemAdminUsername', '__system_admin__')
            ->getQuery()
            ->getResult();

        if (empty($adminUsers)) {
            $io->success('No admin users found in the database.');
            return Command::SUCCESS;
        }

        $count = count($adminUsers);
        $io->warning(sprintf('Found %d admin user(s) in the database:', $count));

        foreach ($adminUsers as $user) {
            $io->text(sprintf('  - ID: %d, Username: %s, Email: %s', 
                $user->getId(), 
                $user->getUsername(), 
                $user->getEmail()
            ));
        }

        if (!$io->confirm('Do you want to remove these admin users?', false)) {
            $io->info('Operation cancelled.');
            return Command::SUCCESS;
        }

        foreach ($adminUsers as $user) {
            $this->entityManager->remove($user);
        }

        $this->entityManager->flush();

        $io->success(sprintf('Successfully removed %d admin user(s) from the database.', $count));
        $io->note('Admin users should only exist in-memory (configured in security.yaml).');

        return Command::SUCCESS;
    }
}

