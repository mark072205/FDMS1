<?php

namespace App\DataFixtures;

use App\Entity\Users;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $username = 'admin';
        $email = 'admin@login.com';
        $password = 'adminuser';

        // Check if user already exists (only when using --append flag)
        // When using --append, we skip if user exists to avoid duplicates
        $existingUser = $manager->getRepository(Users::class)->findOneBy(['username' => $username]);
        if ($existingUser) {
            return; // User already exists, skip creation
        }

        $existingEmail = $manager->getRepository(Users::class)->findOneBy(['email' => $email]);
        if ($existingEmail) {
            return; // Email already exists, skip creation
        }

        // Create admin user
        $admin = new Users();
        $admin->setUsername($username);
        $admin->setEmail($email);
        $admin->setFirstName('Admin');
        $admin->setLastName('User');
        $admin->setRole('admin');
        $admin->setUserType('admin');
        $admin->setBio(null);
        $admin->setIsActive(true);
        $admin->setVerified(true);
        $admin->setCreatedAt(new \DateTimeImmutable());
        $admin->setUpdatedAt(new \DateTime());

        // Hash password before setting (required for admin users, no strict validation)
        $hashedPassword = $this->passwordHasher->hashPassword(
            $admin,
            $password
        );
        $admin->setPassword($hashedPassword);

        try {
            $manager->persist($admin);
            $manager->flush();
        } catch (\Exception $e) {
            // Log error but don't fail fixture loading
            error_log('Error creating admin user in UserFixtures: ' . $e->getMessage());
            throw $e;
        }
    }
}
