<?php

namespace App\Entity;

use App\Enum\TypeDemandeRgpd;
use App\Repository\DemandeRgpdRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Demande envoyée à un membre dans le cadre de l'information RGPD : accusé de lecture, ou choix sur les communications.
 * Seul le hash du jeton est stocké ; le jeton clair ne vit que le temps de l'envoi du mail.
 * La réponse (accord) est conservée comme preuve, avec la version du texte affiché.
 */
#[ORM\Entity(repositoryClass: DemandeRgpdRepository::class)]
#[ORM\Table(name: 'demande_rgpd')]
class DemandeRgpd
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 20, enumType: TypeDemandeRgpd::class)]
    private TypeDemandeRgpd $type;

    /** Hash SHA-256 du jeton présent dans le lien du mail. */
    #[ORM\Column(length: 64, unique: true)]
    private string $tokenHash;

    /** Version du texte d'information affiché au membre (ex. 2026-09). */
    #[ORM\Column(length: 20)]
    private string $version;

    /** Choix du membre pour une demande de communication ; null tant qu'il n'a pas répondu ou pour un accusé de lecture. */
    #[ORM\Column(nullable: true)]
    private ?bool $accord = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $envoyeeLe;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $repondueLe = null;

    /** Jeton clair, disponible uniquement sur l'instance créée (non persisté). */
    private ?string $jeton = null;

    public function __construct(User $user, TypeDemandeRgpd $type, string $version)
    {
        $this->user = $user;
        $this->type = $type;
        $this->version = $version;
        $this->jeton = bin2hex(random_bytes(32));
        $this->tokenHash = hash('sha256', $this->jeton);
        $this->envoyeeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getType(): TypeDemandeRgpd
    {
        return $this->type;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getAccord(): ?bool
    {
        return $this->accord;
    }

    public function getEnvoyeeLe(): \DateTimeImmutable
    {
        return $this->envoyeeLe;
    }

    public function getRepondueLe(): ?\DateTimeImmutable
    {
        return $this->repondueLe;
    }

    public function getJeton(): ?string
    {
        return $this->jeton;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function estRepondue(): bool
    {
        return null !== $this->repondueLe;
    }

    /** Enregistre la réponse du membre. Pour un accusé de lecture, $accord reste null. */
    public function repondre(?bool $accord): void
    {
        $this->accord = $accord;
        $this->repondueLe = new \DateTimeImmutable();
    }
}
