<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make last_name column nullable for admin and staff users
 */
final class Version20251209000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make last_name column nullable to allow admin and staff users to not have a last name';
    }

    public function up(Schema $schema): void
    {
        // Make last_name column nullable
        $this->addSql('ALTER TABLE users MODIFY last_name VARCHAR(100) NULL DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Revert last_name column to NOT NULL (but this will fail if there are NULL values)
        // First, set any NULL values to empty string
        $this->addSql('UPDATE users SET last_name = "" WHERE last_name IS NULL');
        $this->addSql('ALTER TABLE users MODIFY last_name VARCHAR(100) NOT NULL');
    }
}
