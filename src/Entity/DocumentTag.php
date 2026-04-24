<?php

namespace App\Entity;

use App\Repository\DocumentTagRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentTagRepository::class)]
#[ORM\Table(name: 'document_tag')]
#[ORM\UniqueConstraint(name: 'uniq_document_tag_slug', columns: ['slug'])]
#[ORM\HasLifecycleCallbacks]
class DocumentTag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private ?string $name = null;

    #[ORM\Column(length: 140)]
    private ?string $slug = null;

    /** @var Collection<int, DocumentFolder> */
    #[ORM\ManyToMany(targetEntity: DocumentFolder::class, mappedBy: 'tags')]
    private Collection $folders;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->folders = new ArrayCollection();
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
        $this->slug = self::slugify($this->name);

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    /**
     * @return Collection<int, DocumentFolder>
     */
    public function getFolders(): Collection
    {
        return $this->folders;
    }

    public function addFolder(DocumentFolder $folder): static
    {
        if (!$this->folders->contains($folder)) {
            $this->folders->add($folder);
            $folder->addTag($this);
        }

        return $this;
    }

    public function removeFolder(DocumentFolder $folder): static
    {
        if ($this->folders->removeElement($folder)) {
            $folder->removeTag($this);
        }

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    public static function slugify(string $name): string
    {
        $normalized = strtolower(trim($name));
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', '-', (string) $normalized) ?? '';

        return trim($normalized, '-');
    }
}
