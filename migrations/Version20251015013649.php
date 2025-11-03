<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251015013649 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ensure all existing users are set to active by default';
    }

    public function up(Schema $schema): void
    {
        // Set all existing users to active by default
        $this->addSql('UPDATE users SET is_active = 1 WHERE is_active IS NULL OR is_active = 0');
    }

    public function down(Schema $schema): void
    {
        // This migration only sets default values, no rollback needed
        // as we don't want to disable users when rolling back
    }
}
