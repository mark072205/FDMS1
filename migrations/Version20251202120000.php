<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251202120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create settings table for admin configuration';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE settings (id INT AUTO_INCREMENT NOT NULL, updated_by_id INT DEFAULT NULL, setting_key VARCHAR(100) NOT NULL, setting_value LONGTEXT DEFAULT NULL, category VARCHAR(50) NOT NULL, updated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX unique_setting_key (setting_key), INDEX IDX_E545A0C5896DBBDE (updated_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE settings ADD CONSTRAINT FK_E545A0C5896DBBDE FOREIGN KEY (updated_by_id) REFERENCES users (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE settings DROP FOREIGN KEY FK_E545A0C5896DBBDE');
        $this->addSql('DROP TABLE settings');
    }
}
