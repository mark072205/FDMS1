<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251205070000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make user_id nullable in notifications table for in-memory admin notifications';
    }

    public function up(Schema $schema): void
    {
        // Make user_id nullable in notifications table
        // null user_id = notifications for in-memory admins
        $this->addSql('ALTER TABLE notifications MODIFY user_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove any notifications with null user_id before making it NOT NULL again
        $this->addSql('DELETE FROM notifications WHERE user_id IS NULL');
        
        // Make user_id NOT NULL again
        $this->addSql('ALTER TABLE notifications MODIFY user_id INT NOT NULL');
    }
}
