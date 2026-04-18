<?php

namespace App\Entity;

use App\Repository\MemberRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MemberRepository::class)]
#[ORM\Index(name: 'idx_member_email', columns: ['preferred_email'])]
#[ORM\Index(name: 'idx_member_last_first', columns: ['last_name_or_company', 'first_name_or_service'])]
class Member
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?bool $rgpdConsent = null;

    #[ORM\Column(length: 60, nullable: true)]
    private ?string $shortTitle = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $lastNameOrCompany = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $birthName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $firstNameOrService = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $homePhone = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $mobilePhone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $preferredEmail = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $birthOrFoundedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $baptismAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $modificationToApply = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remarks = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $lastContactName = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $contactChannel = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastContactAt = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $longitude = null;

    #[ORM\ManyToOne(inversedBy: 'members')]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Secteur $sector = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isRgpdConsent(): ?bool
    {
        return $this->rgpdConsent;
    }

    public function setRgpdConsent(?bool $rgpdConsent): static
    {
        $this->rgpdConsent = $rgpdConsent;

        return $this;
    }

    public function getShortTitle(): ?string
    {
        return $this->shortTitle;
    }

    public function setShortTitle(?string $shortTitle): static
    {
        $this->shortTitle = $shortTitle;

        return $this;
    }

    public function getLastNameOrCompany(): ?string
    {
        return $this->lastNameOrCompany;
    }

    public function setLastNameOrCompany(?string $lastNameOrCompany): static
    {
        $this->lastNameOrCompany = $lastNameOrCompany;

        return $this;
    }

    public function getBirthName(): ?string
    {
        return $this->birthName;
    }

    public function setBirthName(?string $birthName): static
    {
        $this->birthName = $birthName;

        return $this;
    }

    public function getFirstNameOrService(): ?string
    {
        return $this->firstNameOrService;
    }

    public function setFirstNameOrService(?string $firstNameOrService): static
    {
        $this->firstNameOrService = $firstNameOrService;

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;

        return $this;
    }

    public function getHomePhone(): ?string
    {
        return $this->homePhone;
    }

    public function setHomePhone(?string $homePhone): static
    {
        $this->homePhone = $homePhone;

        return $this;
    }

    public function getMobilePhone(): ?string
    {
        return $this->mobilePhone;
    }

    public function setMobilePhone(?string $mobilePhone): static
    {
        $this->mobilePhone = $mobilePhone;

        return $this;
    }

    public function getPreferredEmail(): ?string
    {
        return $this->preferredEmail;
    }

    public function setPreferredEmail(?string $preferredEmail): static
    {
        $this->preferredEmail = $preferredEmail;

        return $this;
    }

    public function getBirthOrFoundedAt(): ?\DateTimeInterface
    {
        return $this->birthOrFoundedAt;
    }

    public function setBirthOrFoundedAt(?\DateTimeInterface $birthOrFoundedAt): static
    {
        $this->birthOrFoundedAt = $birthOrFoundedAt;

        return $this;
    }

    public function getBaptismAt(): ?\DateTimeInterface
    {
        return $this->baptismAt;
    }

    public function setBaptismAt(?\DateTimeInterface $baptismAt): static
    {
        $this->baptismAt = $baptismAt;

        return $this;
    }

    public function getModificationToApply(): ?string
    {
        return $this->modificationToApply;
    }

    public function setModificationToApply(?string $modificationToApply): static
    {
        $this->modificationToApply = $modificationToApply;

        return $this;
    }

    public function getRemarks(): ?string
    {
        return $this->remarks;
    }

    public function setRemarks(?string $remarks): static
    {
        $this->remarks = $remarks;

        return $this;
    }

    public function getLastContactName(): ?string
    {
        return $this->lastContactName;
    }

    public function setLastContactName(?string $lastContactName): static
    {
        $this->lastContactName = $lastContactName;

        return $this;
    }

    public function getContactChannel(): ?string
    {
        return $this->contactChannel;
    }

    public function setContactChannel(?string $contactChannel): static
    {
        $this->contactChannel = $contactChannel;

        return $this;
    }

    public function getLastContactAt(): ?\DateTimeInterface
    {
        return $this->lastContactAt;
    }

    public function setLastContactAt(?\DateTimeInterface $lastContactAt): static
    {
        $this->lastContactAt = $lastContactAt;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getSector(): ?Secteur
    {
        return $this->sector;
    }

    public function setSector(?Secteur $sector): static
    {
        $this->sector = $sector;

        return $this;
    }
}
