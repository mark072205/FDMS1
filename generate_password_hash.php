<?php
// Simple script to generate password hash
require_once 'vendor/autoload.php';

use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactory;

// Create password hasher factory
$factory = new PasswordHasherFactory([
    'auto' => ['algorithm' => 'auto'],
]);

$hasher = $factory->getPasswordHasher('auto');

// Change this to your desired password
$password = 'adminuser';

// Generate hash
$hash = $hasher->hash($password);

echo "Password: " . $password . "\n";
echo "Hash: " . $hash . "\n";
echo "\nCopy the hash above to your security.yaml file\n";
