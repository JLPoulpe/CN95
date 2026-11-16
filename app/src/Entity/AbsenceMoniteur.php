<?php

namespace App\Entity;

use App\Repository\AbsenceMoniteurRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Absence d'un moniteur à un créneau du planning (le moniteur y est attendu parce qu'il encadre un groupe du créneau). */
#[ORM\Entity(repositoryClass: AbsenceMoniteurRepository::class)]
#[ORM\Table(name: 'absence_moniteur')]
#[ORM\UniqueConstraint(columns: ['creneau_id', 'moniteur_id'])]
class AbsenceMoniteur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'absences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private PlanningCreneau $creneau;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $moniteur;

    /** Qui a déclaré l'absence (le moniteur lui-même ou le bureau). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $declarePar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(PlanningCreneau $creneau, User $moniteur, ?User $declarePar = null)
    {
        $this->creneau = $creneau;
        $this->moniteur = $moniteur;
        $this->declarePar = $declarePar;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreneau(): PlanningCreneau
    {
        return $this->creneau;
    }

    public function getMoniteur(): User
    {
        return $this->moniteur;
    }

    public function getDeclarePar(): ?User
    {
        return $this->declarePar;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
