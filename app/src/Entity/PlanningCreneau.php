<?php

namespace App\Entity;

use App\Enum\Jour;
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

    /** @var Collection<int, LieuActivite> */
    #[ORM\ManyToMany(targetEntity: LieuActivite::class)]
    #[ORM\JoinTable(name: 'planning_creneau_lieu_activite')]
    #[ORM\OrderBy(['ordre' => 'ASC', 'groupe' => 'ASC', 'nom' => 'ASC'])]
    private Collection $lieux;

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
        $this->lieux = new ArrayCollection();
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (0 === $this->lieux->count() && !$this->estAutres()) {
            $context->buildViolation('Choisir au moins un lieu.')->atPath('lieux')->addViolation();
        }
    }

    /** Créneau « Autres » : seule activité « Autres », sans lieu obligatoire. */
    public function estAutres(): bool
    {
        if ($this->activites->isEmpty()) {
            return false;
        }
        foreach ($this->activites as $a) {
            if (Activite::CODE_AUTRES !== $a->getCode()) {
                return false;
            }
        }

        return true;
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

    /** @return Collection<int, LieuActivite> */
    public function getLieux(): Collection
    {
        return $this->lieux;
    }

    public function addLieu(LieuActivite $lieu): static
    {
        if (!$this->lieux->contains($lieu)) {
            $this->lieux->add($lieu);
        }

        return $this;
    }

    public function removeLieu(LieuActivite $lieu): static
    {
        $this->lieux->removeElement($lieu);

        return $this;
    }

    // L'inflecteur de Symfony (anglais) singularise « lieux » en « lieux » : le formulaire
    // (by_reference => false) cherche donc addLieux()/removeLieux().
    public function addLieux(LieuActivite $lieu): static
    {
        return $this->addLieu($lieu);
    }

    public function removeLieux(LieuActivite $lieu): static
    {
        return $this->removeLieu($lieu);
    }

    /** Groupes concernés, dans l'ordre d'affichage (le plus petit « ordre » de chaque groupe fait foi). @return list<string> */
    public function getGroupes(): array
    {
        $groupes = [];
        foreach ($this->lieux as $l) {
            $groupes[$l->getGroupe()] = min($groupes[$l->getGroupe()] ?? PHP_INT_MAX, $l->getOrdre());
        }
        asort($groupes);

        return array_map('strval', array_keys($groupes));
    }

    /** @return list<LieuActivite> */
    public function getLieuxDuGroupe(string $groupe): array
    {
        return array_values($this->lieux->filter(fn (LieuActivite $l) => $l->getGroupe() === $groupe)->toArray());
    }

    /** Libellé complet, ex. « Petit bassin : Ligne 1, Ligne 2 · Salles : Bureau ». */
    public function getLieuxLabel(): string
    {
        return implode(' · ', array_map(
            fn (string $g) => $g.' : '.implode(', ', array_map(fn (LieuActivite $l) => $l->getNom(), $this->getLieuxDuGroupe($g))),
            $this->getGroupes(),
        ));
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
        $this->lieux = new ArrayCollection($this->lieux->toArray());
    }
}
