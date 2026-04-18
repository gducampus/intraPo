<?php

namespace App\Entity;

use App\Repository\ApplicationFeatureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApplicationFeatureRepository::class)]
class ApplicationFeature
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $label = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** Nom de route Symfony — prioritaire sur url */
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $routeName = null;

    /** URL libre — fallback si routeName est vide */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url = null;

    /** Nom d'icône Lucide (ex: "building-2") */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $icon = null;

    /** Catégorie pour regrouper dans le select (ex: "Administration") */
    #[ORM\Column(length: 80, nullable: true)]
    private ?string $category = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;
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

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function setRouteName(?string $routeName): static
    {
        $this->routeName = $routeName;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function __toString(): string
    {
        return $this->label ?? '';
    }
}
