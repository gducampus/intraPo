<?php

namespace App\Entity;

use App\Repository\TrustedDeviceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrustedDeviceRepository::class)]
#[ORM\Table(name: 'trusted_device')]
#[ORM\UniqueConstraint(name: 'uniq_trusted_device_user_hash', columns: ['user_id', 'device_hash'])]
#[ORM\Index(name: 'idx_trusted_device_requested_at', columns: ['requested_at'])]
class TrustedDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PoUser::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PoUser $user = null;

    #[ORM\ManyToOne(targetEntity: PoUser::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?PoUser $approvedBy = null;

    #[ORM\Column(length: 64)]
    private ?string $deviceHash = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $firstIp = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $lastIp = null;

    #[ORM\Column]
    private bool $isApproved = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastSeenAt = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?PoUser
    {
        return $this->user;
    }

    public function setUser(?PoUser $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getApprovedBy(): ?PoUser
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?PoUser $approvedBy): static
    {
        $this->approvedBy = $approvedBy;

        return $this;
    }

    public function getDeviceHash(): ?string
    {
        return $this->deviceHash;
    }

    public function setDeviceHash(string $deviceHash): static
    {
        $this->deviceHash = $deviceHash;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $label = trim((string) $label);
        $this->label = $label === '' ? null : $label;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getFirstIp(): ?string
    {
        return $this->firstIp;
    }

    public function setFirstIp(?string $firstIp): static
    {
        $this->firstIp = $firstIp;

        return $this;
    }

    public function getLastIp(): ?string
    {
        return $this->lastIp;
    }

    public function setLastIp(?string $lastIp): static
    {
        $this->lastIp = $lastIp;

        return $this;
    }

    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    public function setIsApproved(bool $isApproved): static
    {
        $this->isApproved = $isApproved;

        return $this;
    }

    public function getRequestedAt(): ?\DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeImmutable $requestedAt): static
    {
        $this->requestedAt = $requestedAt;

        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getLastSeenAt(): ?\DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(?\DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }
}
