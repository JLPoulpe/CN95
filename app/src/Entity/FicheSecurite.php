<?php

namespace App\Entity;

use App\Repository\FicheSecuriteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Fiche de sécurité d'une invitation : les palanquées préparées par le DP. Créée à la première ouverture. */
#[ORM\Entity(repositoryClass: FicheSecuriteRepository::class)]
#[ORM\Table(name: 'fiche_securite')]
class FicheSecurite
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private InvitationFosse $invitation;

    /** @var Collection<int, Palanquee> */
    #[ORM\OneToMany(targetEntity: Palanquee::class, mappedBy: 'fiche', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['numero' => 'ASC'])]
    private Collection $palanquees;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(InvitationFosse $invitation)
    {
        $this->invitation = $invitation;
        $this->palanquees = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvitation(): InvitationFosse
    {
        return $this->invitation;
    }

    /** @return Collection<int, Palanquee> */
    public function getPalanquees(): Collection
    {
        return $this->palanquees;
    }

    /** Ajoute une palanquée vide, numérotée à la suite des autres. */
    public function ajouterPalanquee(): Palanquee
    {
        $palanquee = new Palanquee($this, $this->palanquees->count() + 1);
        $this->palanquees->add($palanquee);

        return $palanquee;
    }

    /** Supprime une palanquée (ses membres retournent aux restants) et renumérote les suivantes. */
    public function supprimerPalanquee(Palanquee $palanquee): void
    {
        $this->palanquees->removeElement($palanquee);
        foreach (array_values($this->palanquees->toArray()) as $index => $restante) {
            $restante->setNumero($index + 1);
        }
    }

    public function trouverPalanquee(int $id): ?Palanquee
    {
        foreach ($this->palanquees as $palanquee) {
            if ($palanquee->getId() === $id) {
                return $palanquee;
            }
        }

        return null;
    }

    /** Retire un utilisateur de la palanquée où il se trouve, le cas échéant. */
    public function retirer(User $user): void
    {
        foreach ($this->palanquees as $palanquee) {
            $palanquee->retirer($user);
        }
    }

    public function estPlace(User $user): bool
    {
        foreach ($this->palanquees as $palanquee) {
            if ($palanquee->contient($user)) {
                return true;
            }
        }

        return false;
    }
}
