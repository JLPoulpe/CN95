<?php

namespace App\Entity;

use App\Enum\Activite;
use App\Enum\Jour;
use App\Enum\Lieu;
use App\Repository\PlanningCreneauRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

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

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $lignes = null;

    /** @var list<Activite> */
    #[ORM\Column(type: Types::JSON, enumType: Activite::class)]
    #[Assert\Count(min: 1, minMessage: 'Choisir au moins une activité.')]
    private array $activites = [];

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

    public function getLignes(): ?string
    {
        return $this->lignes;
    }

    public function setLignes(?string $lignes): static
    {
        $this->lignes = $lignes;

        return $this;
    }

    /** @return list<Activite> */
    public function getActivites(): array
    {
        return $this->activites;
    }

    /** @param list<Activite> $activites */
    public function setActivites(array $activites): static
    {
        $this->activites = array_values($activites);

        return $this;
    }

    public function getActivitesLabel(): string
    {
        return implode(' + ', array_map(fn (Activite $a) => $a->label(), $this->activites));
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
    }
}
