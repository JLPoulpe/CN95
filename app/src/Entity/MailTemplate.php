<?php

namespace App\Entity;

use App\Enum\TypeMailTemplate;
use App\Repository\MailTemplateRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/** Contenu d'un mail, modifiable depuis l'administration. Le texte est enveloppé par templates/mail/_base.html.twig. */
#[ORM\Entity(repositoryClass: MailTemplateRepository::class)]
#[ORM\Table(name: 'mail_template')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity(fields: ['nom'], message: 'Un modèle porte déjà ce nom.')]
class MailTemplate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private string $nom = '';

    #[ORM\Column(length: 30, enumType: TypeMailTemplate::class)]
    private TypeMailTemplate $type = TypeMailTemplate::Invitation;

    #[ORM\Column(type: Types::TEXT)]
    private string $contenu = '';

    #[ORM\Column]
    private bool $parDefaut = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    #[ORM\PreUpdate]
    public function majDateModification(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getType(): TypeMailTemplate
    {
        return $this->type;
    }

    public function setType(TypeMailTemplate $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function isParDefaut(): bool
    {
        return $this->parDefaut;
    }

    public function setParDefaut(bool $parDefaut): static
    {
        $this->parDefaut = $parDefaut;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function __toString(): string
    {
        return $this->nom;
    }
}
