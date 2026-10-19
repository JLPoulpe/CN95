<?php

namespace App\Entity;

use App\Repository\CommunicationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Communication officielle du bureau, envoyée par mail aux membres. Le titre devient l'objet du mail
 * (préfixé de « CN95 Plongée »), le contenu est enveloppé par templates/mail/_base.html.twig.
 * Un brouillon (envoyeeLe nul) reste modifiable ; une communication envoyée ne l'est plus.
 */
#[ORM\Entity(repositoryClass: CommunicationRepository::class)]
#[ORM\Table(name: 'communication')]
class Communication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Saisir un titre.')]
    #[Assert\Length(max: 150, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private string $titre = '';

    /** HTML de l'éditeur, nettoyé par ContenuMailSanitizer avant enregistrement. */
    #[ORM\Column(type: Types::TEXT)]
    private string $contenu = '';

    /** Auteur ; nul si son compte a été supprimé (purge annuelle). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $auteur = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $envoyeeLe = null;

    /** Nombre de mails mis en file à l'envoi. */
    #[ORM\Column(nullable: true)]
    private ?int $nbDestinataires = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = (string) $titre;

        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = (string) $contenu;

        return $this;
    }

    public function getAuteur(): ?User
    {
        return $this->auteur;
    }

    public function setAuteur(?User $auteur): static
    {
        $this->auteur = $auteur;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getEnvoyeeLe(): ?\DateTimeImmutable
    {
        return $this->envoyeeLe;
    }

    public function getNbDestinataires(): ?int
    {
        return $this->nbDestinataires;
    }

    public function estEnvoyee(): bool
    {
        return null !== $this->envoyeeLe;
    }

    public function marquerEnvoyee(\DateTimeImmutable $quand, int $nbDestinataires): static
    {
        $this->envoyeeLe = $quand;
        $this->nbDestinataires = $nbDestinataires;

        return $this;
    }

    /** Objet du mail : le titre, sur une seule ligne, précédé de « CN95 Plongée ». */
    public function getObjet(): string
    {
        return 'CN95 Plongée – '.trim((string) preg_replace('/\s+/', ' ', $this->titre));
    }
}
