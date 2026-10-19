<?php

namespace App\Entity;

use App\Repository\ActiviteExterneRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activités qui donnent lieu à des invitations (fosse, et à venir).
 * Distinct de l'entité App\Entity\Activite, utilisée par le planning.
 */
#[ORM\Entity(repositoryClass: ActiviteExterneRepository::class)]
#[ORM\Table(name: 'activite_externe')]
class ActiviteExterne
{
    public const FOSSE = 'FOSSE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private string $code = '';

    #[ORM\Column(length: 100)]
    private string $libelle = '';

    #[ORM\Column]
    private bool $necessiteResponsable = true;

    /** Texte pré-rempli dans le commentaire d'une nouvelle invitation (HTML nettoyé par ContenuMailSanitizer). */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $texteParDefaut = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getLibelle(): string
    {
        return $this->libelle;
    }

    public function setLibelle(string $libelle): static
    {
        $this->libelle = $libelle;

        return $this;
    }

    public function isNecessiteResponsable(): bool
    {
        return $this->necessiteResponsable;
    }

    public function setNecessiteResponsable(bool $necessiteResponsable): static
    {
        $this->necessiteResponsable = $necessiteResponsable;

        return $this;
    }

    public function getTexteParDefaut(): ?string
    {
        return $this->texteParDefaut;
    }

    public function setTexteParDefaut(?string $texteParDefaut): static
    {
        $this->texteParDefaut = $texteParDefaut;

        return $this;
    }

    public function __toString(): string
    {
        return $this->libelle;
    }
}
