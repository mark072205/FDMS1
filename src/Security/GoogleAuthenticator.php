<?php

namespace App\Security;

use App\Entity\Users;
use App\Repository\UsersRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

final class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UsersRepository $usersRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        $client = $this->clientRegistry->getClient('google');
        $accessToken = $this->fetchAccessToken($client);

        $oauthMode = null;
        if ($request->hasSession()) {
            $mode = (string) $request->getSession()->get('oauth_mode', '');
            if (in_array($mode, ['login', 'signup'], true)) {
                $oauthMode = $mode;
            }
        }

        $signupRole = null;
        if ($request->hasSession()) {
            $role = (string) $request->getSession()->get('oauth_signup_role', '');
            if (in_array($role, ['client', 'designer'], true)) {
                $signupRole = $role;
            }
        }

        return new SelfValidatingPassport(
            new UserBadge((string) $accessToken->getToken(), function () use ($client, $accessToken, $oauthMode, $signupRole, $request): Users {
                /** @var GoogleUser $googleUser */
                $googleUser = $client->fetchUserFromToken($accessToken);

                $email = $googleUser->getEmail();
                if (!$email) {
                    throw new CustomUserMessageAuthenticationException('Google did not return an email address.');
                }

                $existing = $this->usersRepository->findOneBy(['email' => $email]);
                if ($existing) {
                    if (!$existing->isActive()) {
                        throw new CustomUserMessageAuthenticationException('Your account has been disabled. Please contact an administrator.');
                    }
                    return $existing;
                }

                // Only create users when coming from the signup flow.
                if ($oauthMode !== 'signup') {
                    throw new CustomUserMessageAuthenticationException(
                        sprintf(
                            'Google account %s is not recognized for Google Sign-In on Mark & Motion. Please make sure you are using the same account that you have previously linked.',
                            $email
                        )
                    );
                }

                $user = new Users();
                $user->setEmail($email);

                $firstName = $googleUser->getFirstName() ?: 'Google';
                $lastName = $googleUser->getLastName();
                $user->setFirstName($firstName);
                $user->setLastName($lastName);

                $finalRole = $signupRole ?: 'client';
                $user->setRole($finalRole);
                $user->setUserType($finalRole);
                $user->setIsActive(true);
                $user->setVerified(true);
                $user->setCreatedAt(new \DateTimeImmutable());

                $baseUsername = preg_replace('/[^a-zA-Z0-9_]/', '_', (string) strstr($email, '@', true));
                $baseUsername = $baseUsername ?: 'google_user';

                $username = $baseUsername;
                $suffix = 1;
                while ($this->usersRepository->findOneBy(['username' => $username])) {
                    $suffix++;
                    $username = $baseUsername . '_' . $suffix;
                }
                $user->setUsername($username);

                $randomPassword = bin2hex(random_bytes(20));
                $user->setPassword($this->passwordHasher->hashPassword($user, $randomPassword));

                $this->entityManager->persist($user);
                $this->entityManager->flush();

                if ($request->hasSession()) {
                    $request->getSession()->remove('oauth_signup_role');
                    $request->getSession()->remove('oauth_mode');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof Users) {
            $user->setLastLogin(new \DateTime());
            $user->setLastActivity(new \DateTime());
            $user->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_STAFF', $roles, true)) {
                $route = 'app_dashboard';
            } elseif (in_array('ROLE_DESIGNER', $roles, true)) {
                $route = 'app_designer_homepage';
            } elseif (in_array('ROLE_CLIENT', $roles, true)) {
                $route = 'app_client_homepage';
            } else {
                $route = 'app_landing_page';
            }

            return new RedirectResponse($this->urlGenerator->generate($route));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function onAuthenticationFailure(Request $request, \Symfony\Component\Security\Core\Exception\AuthenticationException $exception): Response
    {
        if ($request->hasSession()) {
            $flashes = $request->getSession()->getBag('flashes');
            if ($flashes instanceof FlashBagInterface) {
                $flashes->add('error', $exception->getMessage());
            }
        }
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }
}

