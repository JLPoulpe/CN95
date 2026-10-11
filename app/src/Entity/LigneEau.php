<?php

namespace App\Entity;

use App\Enum\Lieu;
use App\Repository\LigneEauRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LigneEauRepository::class)]
#[ORM\Table(name: 'ligne_eau')]
#[ORM\UniqueConstraint(columns: ['lieu', 'numero'])]
class LigneEau
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(enumType: Lieu::class, length: 20)]
    private ?Lieu $lieu = null;

    #[ORM\Column]
    private int $numero = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNumero(): int
    {
        return $this->numero;
    }

    public function setNumero(int $numero): static
    {
        $this->numero = $numero;

        return $this;
    }

    public function getLabel(): string
    {
        return sprintf('%s – Ligne %d', $this->lieu?->label(), $this->numero);
    }

    public function __toString(): string
    {
        return $this->getLabel();
    }
}
