<?php

namespace App\Entity;

use App\Enum\StatutInvitationFosse;
use App\Repository\InvitationFosseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: InvitationFosseRepository::class)]
#[ORM\Table(name: 'invitation_fosse')]
#[ORM\UniqueConstraint(name: 'uniq_invitation_fosse_date_activite', columns: ['date_fosse', 'activite_id'])]
#[UniqueEntity(fields: ['date', 'activite'], message: 'Une invitation existe déjà pour cette date et cette activité.', errorPath: 'date')]
class InvitationFosse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'date_fosse', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $date = null;

    /** @var Collection<int, Aptitude> */
    #[ORM\ManyToMany(targetEntity: Aptitude::class)]
    #[ORM\JoinTable(name: 'invitation_fosse_aptitude')]
    #[Assert\Count(min: 1, minMessage: 'Choisir au moins une aptitude.')]
    private Collection $aptitudes;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $dp = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    private ?ActiviteExterne $activite = null;

    #[ORM\Column(length: 20, enumType: StatutInvitationFosse::class)]
    private StatutInvitationFosse $statut = StatutInvitationFosse::Active;

    /** @var Collection<int, ReponseFosse> */
    #[ORM\OneToMany(targetEntity: ReponseFosse::class, mappedBy: 'invitation', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $reponses;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    public function __construct()
    {
        $this->aptitudes = new ArrayCollection();
        $this->reponses = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (null === $this->dp) {
            if ($this->activite?->isNecessiteResponsable()) {
                $context->buildViolation('Choisir un responsable.')->atPath('dp')->addViolation();
            }

            return;
        }

        if (!$this->dp->hasRole(Role::MONITEUR)) {
            $context->buildViolation('Le responsable doit être un moniteur.')->atPath('dp')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;

        return $this;
    }

    /** @return Collection<int, Aptitude> */
    public function getAptitudes(): Collection
    {
        return $this->aptitudes;
    }

    public function addAptitude(Aptitude $aptitude): static
    {
        if (!$this->aptitudes->contains($aptitude)) {
            $this->aptitudes->add($aptitude);
        }

        return $this;
    }

    public function removeAptitude(Aptitude $aptitude): static
    {
        $this->aptitudes->removeElement($aptitude);

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

    public function getActivite(): ?ActiviteExterne
    {
        return $this->activite;
    }

    public function setActivite(?ActiviteExterne $activite): static
    {
        $this->activite = $activite;

        return $this;
    }

    public function getStatut(): StatutInvitationFosse
    {
        return $this->statut;
    }

    public function setStatut(StatutInvitationFosse $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function isAnnulee(): bool
    {
        return StatutInvitationFosse::Annulee === $this->statut;
    }

    public function isPassee(): bool
    {
        return $this->date < new \DateTimeImmutable('today');
    }

    /** Les liens du mail restent valables jusqu'à la fin du lendemain de la fosse. */
    public function isLienExpire(): bool
    {
        return new \DateTimeImmutable('today') > $this->date->modify('+1 day');
    }

    /** Une invitation peut recevoir des envois (relance, annulation) tant qu'elle est active et à venir. */
    public function isEnvoyable(): bool
    {
        return !$this->isAnnulee() && !$this->isPassee();
    }

    /** @return Collection<int, ReponseFosse> */
    public function getReponses(): Collection
    {
        return $this->reponses;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }
}
