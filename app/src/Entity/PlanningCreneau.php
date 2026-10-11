<?php

namespace App\Entity;

use App\Enum\Jour;
use App\Enum\Lieu;
use App\Repository\PlanningCreneauRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PlanningCreneauRepository::class)]
class PlanningCreneau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'creneaux')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?PlanningSemaine $semaine = null;

    #[ORM\Column(enumType: Jour::class, length: 10)]
    #[Assert\NotNull]
    private ?Jour $jour = null;

    #[ORM\Column(enumType: Lieu::class, length: 20)]
    #[Assert\NotNull]
    private ?Lieu $lieu = null;

    /** @var Collection<int, LigneEau> */
    #[ORM\ManyToMany(targetEntity: LigneEau::class)]
    #[ORM\JoinTable(name: 'planning_creneau_ligne_eau')]
    #[ORM\OrderBy(['numero' => 'ASC'])]
    private Collection $lignes;

    /** @var Collection<int, Activite> */
    #[ORM\ManyToMany(targetEntity: Activite::class)]
    #[ORM\JoinTable(name: 'planning_creneau_activite')]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    #[Assert\Count(min: 1, minMessage: 'Choisir au moins une activité.')]
    private Collection $activites;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $libelle = null;

    /** @var Collection<int, Aptitude> */
    #[ORM\ManyToMany(targetEntity: Aptitude::class)]
    #[ORM\JoinTable(name: 'planning_creneau_aptitude')]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $aptitudes;

    public function __construct()
    {
        $this->aptitudes = new ArrayCollection();
        $this->activites = new ArrayCollection();
        $this->lignes = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        foreach ($this->lignes as $ligne) {
            if ($ligne->getLieu() !== $this->lieu) {
                $context->buildViolation('Les lignes doivent appartenir au lieu du créneau.')->atPath('lignes')->addViolation();
                break;
            }
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSemaine(): ?PlanningSemaine
    {
        return $this->semaine;
    }

    public function setSemaine(?PlanningSemaine $semaine): static
    {
        $this->semaine = $semaine;

        return $this;
    }

    public function getJour(): ?Jour
    {
        return $this->jour;
    }

    public function setJour(?Jour $jour): static
    {
        $this->jour = $jour;

        return $this;
    }

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): static
    {
        $this->lieu = $lieu;

        return $this;
    }

    /** @return Collection<int, LigneEau> */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneEau $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
        }

        return $this;
    }

    public function removeLigne(LigneEau $ligne): static
    {
        $this->lignes->removeElement($ligne);

        return $this;
    }

    public function getLignesLabel(): string
    {
        return implode('/', array_map(fn (LigneEau $l) => (string) $l->getNumero(), $this->lignes->toArray()));
    }

    /** @return Collection<int, Activite> */
    public function getActivites(): Collection
    {
        return $this->activites;
    }

    public function addActivite(Activite $activite): static
    {
        if (!$this->activites->contains($activite)) {
            $this->activites->add($activite);
        }

        return $this;
    }

    public function removeActivite(Activite $activite): static
    {
        $this->activites->removeElement($activite);

        return $this;
    }

    public function getActivitesLabel(): string
    {
        return implode(' + ', array_map(fn (Activite $a) => $a->getLibelle(), $this->activites->toArray()));
    }

    public function getLibelle(): ?string
    {
        return $this->libelle;
    }

    public function setLibelle(?string $libelle): static
    {
        $this->libelle = $libelle;

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

    public function __clone()
    {
        $this->id = null;
        $this->semaine = null;
        $this->aptitudes = new ArrayCollection($this->aptitudes->toArray());
        $this->activites = new ArrayCollection($this->activites->toArray());
        $this->lignes = new ArrayCollection($this->lignes->toArray());
    }
}
