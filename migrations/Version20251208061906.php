<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251208061906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename settings table index to unique_setting_key';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Index already exists as 'unique_setting_key' from Version20251202120000
        // This migration is a no-op as the index name is already correct
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        // No rollback needed as this migration doesn't change anything
    }
}
