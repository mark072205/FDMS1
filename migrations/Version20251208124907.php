<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251208124907 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activity_logs (id INT AUTO_INCREMENT NOT NULL, user_id INT DEFAULT NULL, username VARCHAR(50) DEFAULT NULL, role VARCHAR(50) NOT NULL, action VARCHAR(50) NOT NULL, entity_type VARCHAR(100) DEFAULT NULL, entity_id INT DEFAULT NULL, details LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F34B1DCEA76ED395 (user_id), INDEX idx_created_at (created_at), INDEX idx_action (action), INDEX idx_entity_type (entity_type), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE activity_logs ADD CONSTRAINT FK_F34B1DCEA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activity_logs DROP FOREIGN KEY FK_F34B1DCEA76ED395');
        $this->addSql('DROP TABLE activity_logs');
    }
}
