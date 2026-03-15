<?php

namespace App\Repository;

use App\Entity\Settings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settings>
 */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    /**
     * Get a setting value by key
     */
    public function getSetting(string $key, ?string $default = null): ?string
    {
        $setting = $this->findOneBy(['settingKey' => $key]);
        return $setting?->getSettingValue() ?? $default;
    }

    /**
     * Set a setting value by key
     */
    public function setSetting(string $key, ?string $value, string $category = 'general'): Settings
    {
        $setting = $this->findOneBy(['settingKey' => $key]);
        
        if (!$setting) {
            $setting = new Settings();
            $setting->setSettingKey($key);
            $setting->setCategory($category);
        }
        
        $setting->setSettingValue($value);
        $setting->setUpdatedAt(new \DateTimeImmutable());
        
        return $setting;
    }

    /**
     * Get all settings by category
     */
    public function findByCategory(string $category): array
    {
        return $this->findBy(['category' => $category], ['settingKey' => 'ASC']);
    }

    /**
     * Get all settings as an associative array
     */
    public function getAllAsArray(): array
    {
        $settings = $this->findAll();
        $result = [];
        
        foreach ($settings as $setting) {
            $result[$setting->getSettingKey()] = $setting->getSettingValue();
        }
        
        return $result;
    }
}

