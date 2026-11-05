<?php

namespace App\Entity;

use App\Repository\LieuActiviteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/** Lieu où se déroule une activité : une ligne d'un bassin, une salle, le bureau… */
#[ORM\Entity(repositoryClass: LieuActiviteRepository::class)]
#[ORM\Table(name: 'lieu_activite')]
#[ORM\UniqueConstraint(columns: ['groupe', 'nom'])]
class LieuActivite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $nom = '';

    /** Regroupement d'affichage (ex. « Petit bassin », « Salles »). */
    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private string $groupe = '';

    #[ORM\Column]
    private int $ordre = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = trim($nom);

        return $this;
    }

    public function getGroupe(): string
    {
        return $this->groupe;
    }

    public function setGroupe(string $groupe): static
    {
        $this->groupe = trim($groupe);

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function setOrdre(int $ordre): static
    {
        $this->ordre = $ordre;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->groupe === $this->nom ? $this->nom : sprintf('%s – %s', $this->groupe, $this->nom);
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
