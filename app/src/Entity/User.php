<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['username'], message: 'Cet identifiant est déjà utilisé.')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const DOMAINE_COMPTE_LOCAL = 'cn95.local';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private ?string $prenom = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateNaissance = null;

    /** Date d'expiration du certificat médical (CACI). */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $caciExpireLe = null;

    #[ORM\ManyToOne]
    #[Assert\NotNull]
    private ?Aptitude $aptitude = null;

    #[ORM\ManyToOne]
    private ?Aptitude $aptitudePreparee = null;

    /**
     * Aptitudes préparées dont ce moniteur a la charge (groupes encadrés).
     *
     * @var Collection<int, Aptitude>
     */
    #[ORM\ManyToMany(targetEntity: Aptitude::class)]
    #[ORM\JoinTable(name: 'user_aptitude_encadree')]
    #[ORM\OrderBy(['ordre' => 'ASC'])]
    private Collection $aptitudesEncadrees;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    private ?string $username = null;

    /** @var Collection<int, Role> */
    #[ORM\ManyToMany(targetEntity: Role::class)]
    #[ORM\JoinTable(name: 'user_role')]
    private Collection $userRoles;

    #[ORM\Column]
    private ?string $password = null;

    /** Hash SHA-256 du jeton d'activation en attente (le jeton clair n'est jamais stocké). */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $activationTokenHash = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $activationExpireLe = null;

    /** Date de création du compte, utilisée par la purge annuelle (app:rgpd:purger). */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->userRoles = new ArrayCollection();
        $this->aptitudesEncadrees = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /** Compte local (domaine cn95.local) : pas de mail d'activation, mot de passe = identifiant. */
    public function estCompteLocal(): bool
    {
        $domaine = strtolower(substr((string) strrchr((string) $this->email, '@'), 1));

        return self::DOMAINE_COMPTE_LOCAL === $domaine;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;

        return $this;
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function getCaciExpireLe(): ?\DateTimeImmutable
    {
        return $this->caciExpireLe;
    }

    public function setCaciExpireLe(?\DateTimeImmutable $caciExpireLe): static
    {
        $this->caciExpireLe = $caciExpireLe;

        return $this;
    }

    /** Mineur (< 18 ans) à la date donnée (aujourd'hui par défaut). Faux si date de naissance inconnue. */
    public function estMineur(?\DateTimeImmutable $quand = null): bool
    {
        if (null === $this->dateNaissance) {
            return false;
        }

        return $this->dateNaissance->modify('+18 years') > ($quand ?? new \DateTimeImmutable('today'));
    }

    public function caciExpire(?\DateTimeImmutable $quand = null): bool
    {
        return null !== $this->caciExpireLe && $this->caciExpireLe < ($quand ?? new \DateTimeImmutable('today'));
    }

    /** CACI encore valide mais expirant dans moins d'un mois. */
    public function caciBientotExpire(?\DateTimeImmutable $quand = null): bool
    {
        $quand ??= new \DateTimeImmutable('today');

        return null !== $this->caciExpireLe
            && !$this->caciExpire($quand)
            && $this->caciExpireLe < $quand->modify('+1 month');
    }

    public function getAptitude(): ?Aptitude
    {
        return $this->aptitude;
    }

    public function setAptitude(?Aptitude $aptitude): static
    {
        $this->aptitude = $aptitude;

        return $this;
    }

    public function getAptitudePreparee(): ?Aptitude
    {
        return $this->aptitudePreparee;
    }

    public function setAptitudePreparee(?Aptitude $aptitudePreparee): static
    {
        $this->aptitudePreparee = $aptitudePreparee;

        return $this;
    }

    /** @return Collection<int, Aptitude> */
    public function getAptitudesEncadrees(): Collection
    {
        return $this->aptitudesEncadrees;
    }

    public function addAptitudesEncadree(Aptitude $aptitude): static
    {
        if (!$this->aptitudesEncadrees->contains($aptitude)) {
            $this->aptitudesEncadrees->add($aptitude);
        }

        return $this;
    }

    public function removeAptitudesEncadree(Aptitude $aptitude): static
    {
        $this->aptitudesEncadrees->removeElement($aptitude);

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        foreach ($this->userRoles as $role) {
            $roles[] = $role->getSymfonyRole();
        }

        return array_values(array_unique($roles));
    }

    /** @return Collection<int, Role> */
    public function getUserRoles(): Collection
    {
        return $this->userRoles;
    }

    public function addUserRole(Role $role): static
    {
        if (!$this->userRoles->contains($role)) {
            $this->userRoles->add($role);
        }

        return $this;
    }

    public function removeUserRole(Role $role): static
    {
        $this->userRoles->removeElement($role);

        return $this;
    }

    public function hasRole(string $code): bool
    {
        foreach ($this->userRoles as $role) {
            if ($role->getCode() === $code) {
                return true;
            }
        }

        return false;
    }

    /** Un créneau est gérable par groupe dès qu'une de ses aptitudes fait partie des groupes encadrés. */
    public function encadreUneAptitudeDe(PlanningCreneau $creneau): bool
    {
        foreach ($creneau->getAptitudes() as $aptitude) {
            if ($this->aptitudesEncadrees->contains($aptitude)) {
                return true;
            }
        }

        return false;
    }

    public function getNomComplet(): string
    {
        return trim($this->prenom.' '.$this->nom);
    }

    public function __toString(): string
    {
        return $this->getNomComplet();
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /** Crée un lien d'activation valable 7 jours et retourne le jeton à envoyer par mail. */
    public function genererActivation(\DateTimeImmutable $maintenant): string
    {
        $jeton = bin2hex(random_bytes(32));
        $this->activationTokenHash = hash('sha256', $jeton);
        $this->activationExpireLe = $maintenant->modify('+7 days');

        return $jeton;
    }

    public function activationEnAttente(): bool
    {
        return null !== $this->activationTokenHash;
    }

    public function activationValide(string $jeton, \DateTimeImmutable $maintenant): bool
    {
        return $this->activationEnAttente()
            && hash_equals($this->activationTokenHash, hash('sha256', $jeton))
            && $this->activationExpireLe > $maintenant;
    }

    public function terminerActivation(string $motDePasseHache): void
    {
        $this->password = $motDePasseHache;
        $this->activationTokenHash = null;
        $this->activationExpireLe = null;
    }

    public function eraseCredentials(): void
    {
    }

    public function __serialize(): array
    {
        $data = (array) $this;
        unset($data["\0".self::class."\0userRoles"], $data["\0".self::class."\0aptitudesEncadrees"]);
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }
}
