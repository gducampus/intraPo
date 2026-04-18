<?php

namespace App\Entity;

use App\Repository\ModuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModuleRepository::class)]
class Module
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private ?string $title = null;

    #[ORM\Column(length: 255)]
    private ?string $tag = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(nullable: true)]
    private ?bool $isAvailable = null;

    /**
     * @var string[]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $accessRoles = [];

    #[ORM\OneToMany(targetEntity: ModuleFeature::class, mappedBy: 'module', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $moduleFeatures;

    public function __construct()
    {
        $this->moduleFeatures = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(string $tag): static
    {
        $this->tag = $tag;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function isAvailable(): ?bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(?bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;
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

    /** @return Collection<int, ModuleFeature> */
    public function getModuleFeatures(): Collection
    {
        return $this->moduleFeatures;
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
