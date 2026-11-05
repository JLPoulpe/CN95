<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/** Une palanquée : idéalement 5 personnes, dont un moniteur chef de palanquée et 4 accompagnants. */
#[ORM\Entity]
#[ORM\Table(name: 'palanquee')]
class Palanquee
{
    public const TAILLE = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'palanquees')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FicheSecurite $fiche;

    #[ORM\Column]
    private int $numero;

    /** @var Collection<int, PalanqueeMembre> */
    #[ORM\OneToMany(targetEntity: PalanqueeMembre::class, mappedBy: 'palanquee', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $membres;

    public function __construct(FicheSecurite $fiche, int $numero)
    {
        $this->fiche = $fiche;
        $this->numero = $numero;
        $this->membres = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFiche(): FicheSecurite
    {
        return $this->fiche;
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

    /** @return Collection<int, PalanqueeMembre> */
    public function getMembres(): Collection
    {
        return $this->membres;
    }

    public function ajouter(User $user): static
    {
        if (!$this->contient($user)) {
            $this->membres->add(new PalanqueeMembre($this, $user, $this->membres->count()));
        }

        return $this;
    }

    public function retirer(User $user): void
    {
        foreach ($this->membres as $membre) {
            if ($membre->getUser() === $user) {
                $this->membres->removeElement($membre);
            }
        }
        foreach (array_values($this->membres->toArray()) as $index => $membre) {
            $membre->setPosition($index);
        }
    }

    public function contient(User $user): bool
    {
        foreach ($this->membres as $membre) {
            if ($membre->getUser() === $user) {
                return true;
            }
        }

        return false;
    }

    /** Chef de palanquée : le premier moniteur de la palanquée. */
    public function getChef(): ?User
    {
        foreach ($this->membres as $membre) {
            if ($membre->getUser()->hasRole(Role::MONITEUR)) {
                return $membre->getUser();
            }
        }

        return null;
    }

    /** @return list<PalanqueeMembre> Chef d'abord, puis les autres membres dans l'ordre */
    public function getMembresOrdonnes(): array
    {
        $chef = $this->getChef();
        $membres = $this->membres->toArray();
        usort($membres, fn (PalanqueeMembre $a, PalanqueeMembre $b) => [$a->getUser() === $chef ? 0 : 1, $a->getPosition()] <=> [$b->getUser() === $chef ? 0 : 1, $b->getPosition()]);

        return $membres;
    }

    /** Signalements non bloquants : palanquée sans moniteur ou de plus de 5 personnes. */
    public function getAvertissements(): array
    {
        $avertissements = [];
        if ($this->membres->count() > 0 && null === $this->getChef()) {
            $avertissements[] = 'Aucun moniteur dans cette palanquée.';
        }
        if ($this->membres->count() > self::TAILLE) {
            $avertissements[] = sprintf('Plus de %d personnes (%d).', self::TAILLE, $this->membres->count());
        }

        return $avertissements;
    }
}
