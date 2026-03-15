<?php

namespace App\Entity;

use App\Repository\ProposalRepository;
use Doctrine\DBAL\Types\Types;
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

#[ORM\Entity(repositoryClass: ProposalRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_designer_project', columns: ['designer_id', 'project_id'])]
class Proposal
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'proposals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'proposals')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Users $designer = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $proposalText = null;

    #[ORM\Column]
    private ?float $proposedPrice = null;

    #[ORM\Column]
    private ?int $deliveryTime = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $coverLetter = null;

    #[ORM\Column(nullable: true, options: ['default' => 1])]
    private ?int $revisionRounds = 1;

    #[ORM\Column(nullable: true, options: ['default' => false])]
    private ?bool $isFeatured = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $clientNotes = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $respondedAt = null;

    public function __construct()
    {
        $this->status = 'pending';
        $this->revisionRounds = 1;
        $this->isFeatured = false;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getDesigner(): ?Users
    {
        return $this->designer;
    }

    public function setDesigner(?Users $designer): static
    {
        $this->designer = $designer;

        return $this;
    }

    public function getProposalText(): ?string
    {
        return $this->proposalText;
    }

    public function setProposalText(string $proposalText): static
    {
        $this->proposalText = $proposalText;

        return $this;
    }

    public function getProposedPrice(): ?float
    {
        return $this->proposedPrice;
    }

    public function setProposedPrice(float $proposedPrice): static
    {
        $this->proposedPrice = $proposedPrice;

        return $this;
    }

    public function getDeliveryTime(): ?int
    {
        return $this->deliveryTime;
    }

    public function setDeliveryTime(int $deliveryTime): static
    {
        $this->deliveryTime = $deliveryTime;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCoverLetter(): ?string
    {
        return $this->coverLetter;
    }

    public function setCoverLetter(?string $coverLetter): static
    {
        $this->coverLetter = $coverLetter;

        return $this;
    }

    public function getRevisionRounds(): ?int
    {
        return $this->revisionRounds;
    }

    public function setRevisionRounds(?int $revisionRounds): static
    {
        $this->revisionRounds = $revisionRounds;

        return $this;
    }

    public function isFeatured(): ?bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(?bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function getClientNotes(): ?string
    {
        return $this->clientNotes;
    }

    public function setClientNotes(?string $clientNotes): static
    {
        $this->clientNotes = $clientNotes;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getRespondedAt(): ?\DateTime
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?\DateTime $respondedAt): static
    {
        $this->respondedAt = $respondedAt;

        return $this;
    }
}


