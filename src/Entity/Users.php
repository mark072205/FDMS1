<?php

namespace App\Entity;

use App\Repository\UsersRepository;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Mapping\ClassMetadata;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use ApiPlatform\Metadata\Delete;

#[ApiResource(
    operations: [
        new Get(),
        new GetCollection(),
        new Post(),
        new Put(),
        new Delete()
    ]
)]

#[ORM\Entity(repositoryClass: UsersRepository::class)]
class Users implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Username is required.')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Username must be at least {{ limit }} characters long.',
        maxMessage: 'Username cannot be longer than {{ limit }} characters.'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_]+$/',
        message: 'Username can only contain letters, numbers, and underscores.'
    )]
    private ?string $username = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'First name is required.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'First name must be at least {{ limit }} characters long.',
        maxMessage: 'First name cannot be longer than {{ limit }} characters.'
    )]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Last name must be at least {{ limit }} characters long.',
        maxMessage: 'Last name cannot be longer than {{ limit }} characters.',
        groups: ['Default']
    )]
    private ?string $lastName = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Password is required.', groups: ['Default', 'password_required'])]
    #[Assert\Length(
        min: 8,
        minMessage: 'Password must be at least {{ limit }} characters long.',
        groups: ['password_strict']
    )]
    #[Assert\Regex(
        pattern: '/^(?=.*[a-zA-Z0-9@$!%*?&_])[A-Za-z\d@$!%*?&_]{8,}$/',
        message: 'Password must be at least 8 characters and contain at least one of the following: uppercase letter, lowercase letter, number, or special character (@$!%*?&_).',
        groups: ['password_strict']
    )]
    private ?string $password = null;

    #[ORM\Column(length: 20)]
    private ?string $role = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $profilePicture = null;

    #[ORM\ManyToOne(targetEntity: File::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?File $profilePictureFile = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'client')]
    private Collection $projects;

    /**
     * @var Collection<int, Proposal>
     */
    #[ORM\OneToMany(targetEntity: Proposal::class, mappedBy: 'designer')]
    private Collection $proposals;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Email is required.')]
    #[Assert\Email(
        message: 'The email "{{ value }}" is not a valid email address.',
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
        message: 'Please enter a valid email address with @ and domain (e.g., user@example.com).'
    )]
    private ?string $email = null;

    #[ORM\Column(length: 25)]
    private ?string $userType = null;

    #[ORM\Column]
    private ?bool $isActive = null;

    #[ORM\Column]
    private ?bool $verified = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $verificationToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastLogin = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastActivity = null;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
        $this->proposals = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        // Return File path if File entity exists and is active, otherwise return string
        if ($this->profilePictureFile) {
            // Only return path if the file is active (not deleted)
            if ($this->profilePictureFile->isActive()) {
                $path = $this->profilePictureFile->getPath();
                // Normalize path to just filename for template compatibility
                // Templates prepend 'uploads/profile_pictures/', so we need just the filename
                if ($path) {
                    // Extract filename from path (handle both forward and backslashes)
                    $filename = basename($path);
                    // Remove any directory prefixes if present
                    $filename = str_replace('profile_pictures/', '', $filename);
                    $filename = str_replace('profile_pictures\\', '', $filename);
                    return $filename ?: null;
                }
                return null;
            } else {
                // File was deleted, return null (don't modify entity in getter)
                return null;
            }
        }
        // Return old string-based profile picture if it exists
        // Normalize it as well to ensure consistency
        if ($this->profilePicture) {
            // If it's already just a filename (no slashes), return as-is
            if (strpos($this->profilePicture, '/') === false && strpos($this->profilePicture, '\\') === false) {
        return $this->profilePicture;
            }
            // Extract filename from path
            $filename = basename($this->profilePicture);
            // Remove any directory prefixes if present
            $filename = str_replace('profile_pictures/', '', $filename);
            $filename = str_replace('profile_pictures\\', '', $filename);
            $filename = str_replace('uploads/profile_pictures/', '', $filename);
            $filename = str_replace('uploads\\profile_pictures\\', '', $filename);
            return $filename ?: null;
        }
        return null;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;
        return $this;
    }

    public function getProfilePictureFile(): ?File
    {
        return $this->profilePictureFile;
    }

    public function setProfilePictureFile(?File $profilePictureFile): static
    {
        $this->profilePictureFile = $profilePictureFile;
        // Sync the string field for backward compatibility
        // Store just the filename, not the full path, to match template expectations
        if ($profilePictureFile) {
            $path = $profilePictureFile->getPath();
            if ($path) {
                // Extract just the filename for backward compatibility
                $filename = basename($path);
                // Remove any directory prefixes if present
                $filename = str_replace('profile_pictures/', '', $filename);
                $filename = str_replace('profile_pictures\\', '', $filename);
                $this->profilePicture = $filename ?: null;
            } else {
                $this->profilePicture = null;
            }
        } else {
            $this->profilePicture = null;
        }
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setClient($this);
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project)) {
            // set the owning side to null (unless already changed)
            if ($project->getClient() === $this) {
                $project->setClient(null);
            }
        }

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserType(): ?string
    {
        return $this->userType;
    }

    public function setUserType(string $userType): static
    {
        $this->userType = $userType;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->verified;
    }

    public function setVerified(bool $verified): static
    {
        $this->verified = $verified;

        return $this;
    }

    public function getVerificationToken(): ?string
    {
        return $this->verificationToken;
    }

    public function setVerificationToken(?string $verificationToken): static
    {
        $this->verificationToken = $verificationToken;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getLastLogin(): ?\DateTime
    {
        return $this->lastLogin;
    }

    public function setLastLogin(\DateTime $lastLogin): static
    {
        $this->lastLogin = $lastLogin;

        return $this;
    }

    public function getLastActivity(): ?\DateTime
    {
        return $this->lastActivity;
    }

    public function setLastActivity(?\DateTime $lastActivity): static
    {
        $this->lastActivity = $lastActivity;

        return $this;
    }

    /**
     * @return Collection<int, Proposal>
     */
    public function getProposals(): Collection
    {
        return $this->proposals;
    }

    public function addProposal(Proposal $proposal): static
    {
        if (!$this->proposals->contains($proposal)) {
            $this->proposals->add($proposal);
            $proposal->setDesigner($this);
        }

        return $this;
    }

    public function removeProposal(Proposal $proposal): static
    {
        if ($this->proposals->removeElement($proposal)) {
            // set the owning side to null (unless already changed)
            if ($proposal->getDesigner() === $this) {
                $proposal->setDesigner(null);
            }
        }

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        // Map the role field to Symfony roles
        $roles = [];
        
        if ($this->role === 'admin') {
            $roles[] = 'ROLE_ADMIN';
        } elseif ($this->role === 'staff') {
            // Staff has its own role but also gets admin access through security config
            $roles[] = 'ROLE_STAFF';
        } elseif ($this->role === 'designer') {
            $roles[] = 'ROLE_DESIGNER';
        } elseif ($this->role === 'client') {
            $roles[] = 'ROLE_CLIENT';
        }
        
        return array_unique($roles);
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }
}
