<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251014225620 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Populate categories table with initial design categories';
    }

    public function up(Schema $schema): void
    {
        // Insert initial design categories
        $categories = [
            ['Logo Design', 'Custom logos, brand marks, wordmarks, and complete logo packages'],
            ['Brand Identity', 'Complete brand identity systems, style guides, and visual branding'],
            ['UI/UX Design', 'User interface design, user experience, wireframes, and prototypes'],
            ['Graphic Design', 'Posters, flyers, social media graphics, and promotional materials'],
            ['Illustration', 'Custom illustrations, digital art, character design, and icon sets'],
            ['Print Design', 'Brochures, business cards, catalogs, and print-ready materials'],
            ['Packaging Design', 'Product packaging, labels, box design, and packaging mockups'],
            ['Motion Graphics', 'Animated logos, video graphics, 2D/3D animation, and visual effects'],
        ];

        foreach ($categories as $category) {
            $this->addSql('INSERT INTO category (name, description) VALUES (?, ?)', $category);
        }
    }

    public function down(Schema $schema): void
    {
        // Remove the inserted categories
        $categoryNames = [
            'Logo Design',
            'Brand Identity',
            'UI/UX Design',
            'Graphic Design',
            'Illustration',
            'Print Design',
            'Packaging Design',
            'Motion Graphics',
        ];

        foreach ($categoryNames as $name) {
            $this->addSql('DELETE FROM category WHERE name = ?', [$name]);
        }
    }
}
