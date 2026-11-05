<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/** Placement d'un membre dans une palanquée. */
#[ORM\Entity]
#[ORM\Table(name: 'palanquee_membre')]
class PalanqueeMembre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'membres')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Palanquee $palanquee;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column]
    private int $position;

    public function __construct(Palanquee $palanquee, User $user, int $position)
    {
        $this->palanquee = $palanquee;
        $this->user = $user;
        $this->position = $position;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPalanquee(): Palanquee
    {
        return $this->palanquee;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }
}
