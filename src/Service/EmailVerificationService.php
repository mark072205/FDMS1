<?php

namespace App\Service;

use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailVerificationService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private string $fromAddress,
        private string $fromName,
    ) {}

    /**
     * Generate a unique verification token
     */
    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Send verification email to user
     *
     * @param string|null $logoUrl Absolute URL to the logo image (e.g. https://yoursite.com/img/logomain.png)
     */
    public function sendVerificationEmail(Users $user, string $verificationUrl, ?string $logoUrl = null): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($user->getEmail()))
            ->subject('Please verify your email address')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
                'logoUrl' => $logoUrl,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Verify a token and mark user as verified
     */
    public function verifyToken(string $token): ?Users
    {
        $user = $this->entityManager
            ->getRepository(Users::class)
            ->findOneBy(['verificationToken' => $token]);

        if (!$user) {
            return null;
        }

        // Mark user as verified
        $user->setVerified(true);
        $user->setVerificationToken(null); // Clear the token

        $this->entityManager->flush();

        return $user;
    }

    /**
     * Check if a user needs verification
     */
    public function needsVerification(Users $user): bool
    {
        return !$user->isVerified();
    }
}