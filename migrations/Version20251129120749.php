<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251129120749 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create files table and add profile_picture_file_id to users table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE files (id INT AUTO_INCREMENT NOT NULL, uploaded_by_id INT DEFAULT NULL, filename VARCHAR(255) NOT NULL, path VARCHAR(500) NOT NULL, size INT NOT NULL, mime_type VARCHAR(100) NOT NULL, type VARCHAR(50) NOT NULL, extension VARCHAR(20) DEFAULT NULL, uploaded_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', is_active TINYINT(1) NOT NULL, usage_count INT NOT NULL, description LONGTEXT DEFAULT NULL, INDEX IDX_6354059A2B28FE8 (uploaded_by_id), INDEX idx_file_type (type), INDEX idx_file_uploaded_at (uploaded_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE files ADD CONSTRAINT FK_6354059A2B28FE8 FOREIGN KEY (uploaded_by_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE proposal RENAME INDEX IDX_BFE5947225F2704E TO IDX_BFE59472CFC54FAB');
        $this->addSql('ALTER TABLE users ADD profile_picture_file_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9E3AB2394 FOREIGN KEY (profile_picture_file_id) REFERENCES files (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1483A5E9E3AB2394 ON users (profile_picture_file_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9E3AB2394');
        $this->addSql('ALTER TABLE files DROP FOREIGN KEY FK_6354059A2B28FE8');
        $this->addSql('DROP TABLE files');
        $this->addSql('DROP INDEX IDX_1483A5E9E3AB2394 ON users');
        $this->addSql('ALTER TABLE users DROP profile_picture_file_id');
        $this->addSql('ALTER TABLE proposal RENAME INDEX IDX_BFE59472CFC54FAB TO IDX_BFE5947225F2704E');
    }
}
