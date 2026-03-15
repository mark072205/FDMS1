<?php

namespace App\Service;

use Symfony\Component\Yaml\Yaml;

class SecurityConfigService
{
    private string $securityConfigPath;

    public function __construct(
        string $projectDir
    ) {
        $this->securityConfigPath = $projectDir . '/config/packages/security.yaml';
    }
    
    public function getSecurityConfigPath(): string
    {
        return $this->securityConfigPath;
    }

    /**
     * Update admin credentials in security.yaml (replaces all admin users)
     */
    public function updateAdminCredentials(string $username, ?string $password = null): bool
    {
        try {
            // Read current security.yaml
            $yamlContent = file_get_contents($this->securityConfigPath);
            $config = Yaml::parse($yamlContent);

            // Get current password hash if password is not provided
            // First try to find any existing admin user to get the current password
            $currentPasswordHash = null;
            if (isset($config['security']['providers']['admin_provider']['memory']['users'])) {
                $users = $config['security']['providers']['admin_provider']['memory']['users'];
                // Try to find password from existing admin user (any admin user will have the same password)
                foreach ($users as $key => $value) {
                    if (isset($value['roles']) && in_array('ROLE_ADMIN', $value['roles']) && isset($value['password'])) {
                        $currentPasswordHash = $value['password'];
                        break; // Found a password hash, use it
                    }
                }
            }

            // Hash new password if provided
            $passwordHash = $password ? $this->hashPassword($password) : $currentPasswordHash;

            if (!$passwordHash) {
                return false;
            }

            // Remove old admin entries
            if (isset($config['security']['providers']['admin_provider']['memory']['users'])) {
                $users = $config['security']['providers']['admin_provider']['memory']['users'];
                foreach ($users as $key => $value) {
                    if (isset($value['roles']) && in_array('ROLE_ADMIN', $value['roles'])) {
                        unset($config['security']['providers']['admin_provider']['memory']['users'][$key]);
                    }
                }
            }

            // Add new admin user
            $config['security']['providers']['admin_provider']['memory']['users'][$username] = [
                'password' => $passwordHash,
                'roles' => ['ROLE_ADMIN']
            ];

            // Also add email version if username is not an email
            if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $emailUsername = $username . '@login.com';
                $config['security']['providers']['admin_provider']['memory']['users'][$emailUsername] = [
                    'password' => $passwordHash,
                    'roles' => ['ROLE_ADMIN']
                ];
            }

            // Write back to file
            $yaml = Yaml::dump($config, 4, 2);
            file_put_contents($this->securityConfigPath, $yaml);

            return true;
        } catch (\Exception $e) {
            error_log('Error updating security config: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Add a new admin user to security.yaml (keeps existing admin users)
     */
    public function addAdminUser(string $username, string $password): bool
    {
        try {
            // Read current security.yaml
            $yamlContent = file_get_contents($this->securityConfigPath);
            $config = Yaml::parse($yamlContent);

            // Hash the password
            $passwordHash = $this->hashPassword($password);

            if (!$passwordHash) {
                return false;
            }

            // Initialize users array if it doesn't exist
            if (!isset($config['security']['providers']['admin_provider']['memory']['users'])) {
                $config['security']['providers']['admin_provider']['memory']['users'] = [];
            }

            // Check if username already exists
            if (isset($config['security']['providers']['admin_provider']['memory']['users'][$username])) {
                return false; // Username already exists
            }

            // Add new admin user
            $config['security']['providers']['admin_provider']['memory']['users'][$username] = [
                'password' => $passwordHash,
                'roles' => ['ROLE_ADMIN']
            ];

            // Also add email version if username is not an email
            if (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $emailUsername = $username . '@login.com';
                if (!isset($config['security']['providers']['admin_provider']['memory']['users'][$emailUsername])) {
                    $config['security']['providers']['admin_provider']['memory']['users'][$emailUsername] = [
                        'password' => $passwordHash,
                        'roles' => ['ROLE_ADMIN']
                    ];
                }
            }

            // Write back to file
            $yaml = Yaml::dump($config, 4, 2);
            file_put_contents($this->securityConfigPath, $yaml);

            return true;
        } catch (\Exception $e) {
            error_log('Error adding admin user to security config: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hash password using bcrypt (same as Symfony's default)
     */
    private function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]);
    }

    /**
     * Get current admin username from security.yaml
     */
    public function getCurrentAdminUsername(): ?string
    {
        try {
            $yamlContent = file_get_contents($this->securityConfigPath);
            $config = Yaml::parse($yamlContent);

            if (isset($config['security']['providers']['admin_provider']['memory']['users'])) {
                $users = $config['security']['providers']['admin_provider']['memory']['users'];
                foreach ($users as $key => $value) {
                    if (isset($value['roles']) && in_array('ROLE_ADMIN', $value['roles'])) {
                        // Return the first non-email admin username
                        if (!str_contains($key, '@')) {
                            return $key;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('Error reading security config: ' . $e->getMessage());
        }

        return 'admin'; // Default
    }
}

