<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Placement d'un membre dans une palanquée : un utilisateur ou l'une des étiquettes « Moniteur extérieur » (sans compte). */
#[ORM\Entity]
#[ORM\Table(name: 'palanquee_membre')]
class PalanqueeMembre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Palanquee $palanquee;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $user;

    /** Numéro (1 à Palanquee::EXTERIEURS) de l'étiquette « Moniteur extérieur », nul pour un utilisateur. */
    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $exterieur;

    #[ORM\Column]
    private int $position;

    public function __construct(Palanquee $palanquee, ?User $user, int $position, ?int $exterieur = null)
    {
        $this->palanquee = $palanquee;
        $this->user = $user;
        $this->position = $position;
        $this->exterieur = $exterieur;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPalanquee(): Palanquee
    {
        return $this->palanquee;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getExterieur(): ?int
    {
        return $this->exterieur;
    }

    public function estExterieur(): bool
    {
        return null !== $this->exterieur;
    }

    /** Moniteur, utilisateur ou extérieur : peut être chef de palanquée. */
    public function estMoniteur(): bool
    {
        return $this->estExterieur() || ($this->user?->hasRole(Role::MONITEUR) ?? false);
    }

    public static function libelleExterieur(int $numero): string
    {
        return 'Moniteur extérieur '.$numero;
    }

    public function getLibelle(): string
    {
        return null !== $this->exterieur ? self::libelleExterieur($this->exterieur) : trim($this->user?->getPrenom().' '.$this->user?->getNom());
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
