<?php

namespace App\Command;

use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:restore-categories',
    description: 'Restore categories with proper ID sequence (Logo Design first)',
)]
class RestoreCategoriesCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force restoration without confirmation')
            ->setHelp('This command will restore categories with proper ID sequence, ensuring Logo Design is first.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Check if force option is used
        $force = $input->getOption('force');
        
        if (!$force) {
            $io->warning('This will delete all existing categories and recreate them with proper IDs!');
            $io->note('Logo Design will be ID 1, followed by other categories in order.');
            
            if (!$io->confirm('Are you sure you want to continue?', false)) {
                $io->info('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        try {
            // Define categories in the desired order (Logo Design first)
            $categories = [
                [
                    'name' => 'Logo Design',
                    'description' => 'Custom logos, brand marks, wordmarks, and complete logo packages'
                ],
                [
                    'name' => 'Brand Identity',
                    'description' => 'Complete brand identity systems, style guides, and visual branding'
                ],
                [
                    'name' => 'UI/UX Design',
                    'description' => 'User interface design, user experience, wireframes, and prototypes'
                ],
                [
                    'name' => 'Graphic Design',
                    'description' => 'Posters, flyers, social media graphics, and promotional materials'
                ],
                [
                    'name' => 'Illustration',
                    'description' => 'Custom illustrations, digital art, character design, and icon sets'
                ],
                [
                    'name' => 'Print Design',
                    'description' => 'Brochures, business cards, catalogs, and print-ready materials'
                ],
                [
                    'name' => 'Packaging Design',
                    'description' => 'Product packaging, labels, box design, and packaging mockups'
                ],
                [
                    'name' => 'Motion Graphics',
                    'description' => 'Animated logos, video graphics, 2D/3D animation, and visual effects'
                ]
            ];

            // Get count of existing categories
            $existingCount = $this->entityManager->getRepository(Category::class)->count([]);
            
            if ($existingCount > 0) {
                $io->info("Found {$existingCount} existing categories. Deleting them...");
                
                // Delete all existing categories
                $this->entityManager->createQuery('DELETE FROM App\Entity\Category c')->execute();
                $this->entityManager->flush();
                
                $io->info('Existing categories deleted.');
            }

            // Reset auto-increment to start from 1
            $this->entityManager->getConnection()->executeStatement('ALTER TABLE category AUTO_INCREMENT = 1');

            // Create new categories in the correct order
            $io->info('Creating categories with proper ID sequence...');
            
            foreach ($categories as $index => $categoryData) {
                $category = new Category();
                $category->setName($categoryData['name']);
                $category->setDescription($categoryData['description']);
                
                $this->entityManager->persist($category);
                $this->entityManager->flush();
                
                $io->text("✓ Created: {$categoryData['name']} (ID: {$category->getId()})");
            }
            
            $io->success("Successfully restored " . count($categories) . " categories with proper ID sequence!");
            $io->note('Logo Design is now ID 1 as requested.');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $io->error('An error occurred while restoring categories: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
