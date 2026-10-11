<?php

namespace App\Entity;

use App\Enum\StatutReponseFosse;
use App\Repository\ReponseFosseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Réponse d'un membre à une invitation. Créée à l'envoi de l'invitation, en attente jusqu'à ce que le membre réponde. */
#[ORM\Entity(repositoryClass: ReponseFosseRepository::class)]
#[ORM\Table(name: 'reponse_fosse')]
#[ORM\UniqueConstraint(columns: ['invitation_id', 'user_id'])]
class ReponseFosse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reponses')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private InvitationFosse $invitation;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 20, enumType: StatutReponseFosse::class)]
    private StatutReponseFosse $statut = StatutReponseFosse::EnAttente;

    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $repondueAt = null;

    public function __construct(InvitationFosse $invitation, User $user)
    {
        $this->invitation = $invitation;
        $this->user = $user;
        $this->token = bin2hex(random_bytes(32));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvitation(): InvitationFosse
    {
        return $this->invitation;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getStatut(): StatutReponseFosse
    {
        return $this->statut;
    }

    public function setStatut(StatutReponseFosse $statut): static
    {
        $this->statut = $statut;
        $this->repondueAt = new \DateTimeImmutable();

        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getRepondueAt(): ?\DateTimeImmutable
    {
        return $this->repondueAt;
    }

    public function aRepondu(): bool
    {
        return StatutReponseFosse::EnAttente !== $this->statut;
    }
}
