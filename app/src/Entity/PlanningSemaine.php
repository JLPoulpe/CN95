<?php

namespace App\Entity;

use App\Repository\PlanningSemaineRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PlanningSemaineRepository::class)]
#[UniqueEntity(fields: ['lundi'], message: 'Cette semaine existe déjà.')]
class PlanningSemaine
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, unique: true)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $lundi = null;

    #[ORM\Column]
    private bool $fermee = false;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $motif = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?User $dp = null;

    /** @var Collection<int, PlanningCreneau> */
    #[ORM\OneToMany(targetEntity: PlanningCreneau::class, mappedBy: 'semaine', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['jour' => 'ASC', 'lieu' => 'ASC', 'id' => 'ASC'])]
    #[Assert\Valid]
    private Collection $creneaux;

    public function __construct()
    {
        $this->creneaux = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->lundi && '1' !== $this->lundi->format('N')) {
            $context->buildViolation('La date doit être un lundi.')->atPath('lundi')->addViolation();
        }
        if ($this->dp && !$this->dp->hasRole(Role::MONITEUR)) {
            $context->buildViolation('Le DP doit être un moniteur.')->atPath('dp')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLundi(): ?\DateTimeImmutable
    {
        return $this->lundi;
    }

    public function setLundi(?\DateTimeImmutable $lundi): static
    {
        $this->lundi = $lundi;

        return $this;
    }

    public function getVendredi(): ?\DateTimeImmutable
    {
        return $this->lundi?->modify('+4 days');
    }

    public function isFermee(): bool
    {
        return $this->fermee;
    }

    public function setFermee(bool $fermee): static
    {
        $this->fermee = $fermee;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getDp(): ?User
    {
        return $this->dp;
    }

    public function setDp(?User $dp): static
    {
        $this->dp = $dp;

        return $this;
    }

    /** @return Collection<int, PlanningCreneau> */
    public function getCreneaux(): Collection
    {
        return $this->creneaux;
    }

    public function addCreneau(PlanningCreneau $creneau): static
    {
        if (!$this->creneaux->contains($creneau)) {
            $this->creneaux->add($creneau);
            $creneau->setSemaine($this);
        }

        return $this;
    }

    public function removeCreneau(PlanningCreneau $creneau): static
    {
        $this->creneaux->removeElement($creneau);

        return $this;
    }
}
