<?php

namespace App\Entity;

use App\Repository\DocumentItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentItemRepository::class)]
#[ORM\Table(name: 'document_item')]
#[ORM\HasLifecycleCallbacks]
class DocumentItem
{
    public const TYPE_VIDEO_LINK = 'video_link';
    public const TYPE_EXTERNAL_LINK = 'external_link';
    public const TYPE_UPLOADED_FILE = 'uploaded_file';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: DocumentFolder::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?DocumentFolder $folder = null;

    #[ORM\Column(length: 180)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 30)]
    private string $itemType = self::TYPE_EXTERNAL_LINK;

    #[ORM\Column(length: 800, nullable: true)]
    private ?string $externalUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $storedFilename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $originalFilename = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFolder(): ?DocumentFolder
    {
        return $this->folder;
    }

    public function setFolder(?DocumentFolder $folder): static
    {
        $this->folder = $folder;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = trim($title);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $description = trim((string) $description);
        $this->description = $description === '' ? null : $description;

        return $this;
    }

    public function getItemType(): string
    {
        return $this->itemType;
    }

    public function setItemType(string $itemType): static
    {
        $allowed = [
            self::TYPE_VIDEO_LINK,
            self::TYPE_EXTERNAL_LINK,
            self::TYPE_UPLOADED_FILE,
        ];

        if (!in_array($itemType, $allowed, true)) {
            $itemType = self::TYPE_EXTERNAL_LINK;
        }

        $this->itemType = $itemType;

        return $this;
    }

    public function getExternalUrl(): ?string
    {
        return $this->externalUrl;
    }

    public function setExternalUrl(?string $externalUrl): static
    {
        $externalUrl = trim((string) $externalUrl);
        $this->externalUrl = $externalUrl === '' ? null : $externalUrl;

        return $this;
    }

    public function getStoredFilename(): ?string
    {
        return $this->storedFilename;
    }

    public function setStoredFilename(?string $storedFilename): static
    {
        $storedFilename = trim((string) $storedFilename);
        $this->storedFilename = $storedFilename === '' ? null : $storedFilename;

        return $this;
    }

    public function getOriginalFilename(): ?string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(?string $originalFilename): static
    {
        $originalFilename = trim((string) $originalFilename);
        $this->originalFilename = $originalFilename === '' ? null : $originalFilename;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): static
    {
        $mimeType = trim((string) $mimeType);
        $this->mimeType = $mimeType === '' ? null : $mimeType;

        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = max(0, $position);

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function isVideoLink(): bool
    {
        return $this->itemType === self::TYPE_VIDEO_LINK;
    }

    public function isExternalLink(): bool
    {
        return $this->itemType === self::TYPE_EXTERNAL_LINK;
    }

    public function isUploadedFile(): bool
    {
        return $this->itemType === self::TYPE_UPLOADED_FILE;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
