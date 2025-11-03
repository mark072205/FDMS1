<?php

namespace App\Security;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Symfony\Component\Security\Core\Exception\AccountExpiredException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class ActiveUserProvider implements UserProviderInterface
{
    private UsersRepository $userRepository;

    public function __construct(UsersRepository $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Users) {
            throw new \InvalidArgumentException('User must be an instance of Users');
        }

        // Refresh using email if possible; otherwise fall back to username
        $refreshedUser = $this->userRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        if (!$refreshedUser) {
            $refreshedUser = $this->userRepository->findOneBy(['username' => $user->getUserIdentifier()]);
        }
        
        if (!$refreshedUser) {
            throw new AccountExpiredException('User not found');
        }

        // Check if user is active
        if (!$refreshedUser->isActive()) {
            throw new CustomUserMessageAuthenticationException('Your account has been disabled. Please contact an administrator.');
        }

        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return Users::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Try email first
        $user = $this->userRepository->findOneBy(['email' => $identifier]);

        // If not found by email, try username
        if (!$user) {
            $user = $this->userRepository->findOneBy(['username' => $identifier]);
        }

        if (!$user) {
            throw new CustomUserMessageAuthenticationException('Invalid credentials.');
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('Your account has been disabled. Please contact an administrator.');
        }

        return $user;
    }
}
