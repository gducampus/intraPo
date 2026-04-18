<?php

namespace App\Entity;

use App\Repository\DocumentFolderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentFolderRepository::class)]
#[ORM\Table(name: 'document_folder')]
#[ORM\HasLifecycleCallbacks]
class DocumentFolder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $name = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column]
    private bool $isSecured = false;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $accessRoles = [];

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?self $parent = null;

    /** @var Collection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    private Collection $children;

    /** @var Collection<int, DocumentItem> */
    #[ORM\OneToMany(targetEntity: DocumentItem::class, mappedBy: 'folder', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'title' => 'ASC'])]
    private Collection $items;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = trim($name);

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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = max(0, $position);

        return $this;
    }

    public function isSecured(): bool
    {
        return $this->isSecured;
    }

    public function setIsSecured(bool $isSecured): static
    {
        $this->isSecured = $isSecured;

        return $this;
    }

    /**
     * @return string[]
     */
    public function getAccessRoles(): array
    {
        $roles = isset($this->accessRoles) && is_array($this->accessRoles)
            ? $this->accessRoles
            : [];

        return array_values(array_unique(array_filter(
            array_map([$this, 'normalizeRoleCode'], $roles),
            static fn (string $role): bool => $role !== ''
        )));
    }

    /**
     * @param string[] $accessRoles
     */
    public function setAccessRoles(array $accessRoles): static
    {
        $this->accessRoles = array_values(array_unique(array_filter(
            array_map([$this, 'normalizeRoleCode'], $accessRoles),
            static fn (string $role): bool => $role !== ''
        )));

        return $this;
    }

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): static
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): static
    {
        if ($this->children->removeElement($child) && $child->getParent() === $this) {
            $child->setParent(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, DocumentItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(DocumentItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setFolder($this);
        }

        return $this;
    }

    public function removeItem(DocumentItem $item): static
    {
        if ($this->items->removeElement($item) && $item->getFolder() === $this) {
            $item->setFolder(null);
        }

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

    public function getDepth(): int
    {
        $depth = 0;
        $cursor = $this->getParent();
        while ($cursor instanceof self) {
            ++$depth;
            $cursor = $cursor->getParent();
        }

        return $depth;
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

    private function normalizeRoleCode(string $role): string
    {
        $normalized = strtoupper(trim($role));
        $normalized = preg_replace('/[^A-Z0-9_]+/', '_', $normalized) ?? '';
        $normalized = trim($normalized, '_');

        if ($normalized === '') {
            return '';
        }

        if (!str_starts_with($normalized, 'ROLE_')) {
            $normalized = 'ROLE_'.$normalized;
        }

        return $normalized;
    }
}
