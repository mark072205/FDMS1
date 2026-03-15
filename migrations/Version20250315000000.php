<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add verification_token to users for email verification (designer/client must verify before login).
 */
final class Version20250315000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add verification_token column to users table for email verification flow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD verification_token VARCHAR(64) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP verification_token');
    }
}
