<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251015020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create proposal table with relationships to project and users';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE proposal (id INT AUTO_INCREMENT NOT NULL, project_id INT NOT NULL, designer_id INT NOT NULL, proposal_text LONGTEXT NOT NULL, proposed_price DOUBLE PRECISION NOT NULL, delivery_time INT NOT NULL, status VARCHAR(20) NOT NULL, cover_letter LONGTEXT DEFAULT NULL, revision_rounds INT DEFAULT 1, is_featured TINYINT(1) DEFAULT 0, client_notes LONGTEXT DEFAULT NULL, rejection_reason LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL, responded_at DATETIME DEFAULT NULL, INDEX IDX_BFE59472166D1F9C (project_id), INDEX IDX_BFE5947225F2704E (designer_id), UNIQUE INDEX unique_designer_project (designer_id, project_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE proposal ADD CONSTRAINT FK_BFE59472166D1F9C FOREIGN KEY (project_id) REFERENCES project (id)');
        $this->addSql('ALTER TABLE proposal ADD CONSTRAINT FK_BFE5947225F2704E FOREIGN KEY (designer_id) REFERENCES users (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE proposal DROP FOREIGN KEY FK_BFE59472166D1F9C');
        $this->addSql('ALTER TABLE proposal DROP FOREIGN KEY FK_BFE5947225F2704E');
        $this->addSql('DROP TABLE proposal');
    }
}











