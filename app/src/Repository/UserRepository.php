<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface, UserLoaderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Connexion par identifiant ou par adresse e-mail. L'identifiant est cherché en premier ; l'e-mail est comparé
     * sans tenir compte de la casse.
     */
    public function loadUserByIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        return $this->findOneBy(['username' => $identifier])
            ?? $this->createQueryBuilder('u')
                ->where('LOWER(u.email) = LOWER(:identifiant)')->setParameter('identifiant', $identifier)
                ->setMaxResults(1)
                ->getQuery()->getOneOrNullResult();
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Membres dont l'aptitude principale figure dans la liste.
     *
     * @param Aptitude[] $aptitudes
     *
     * @return User[]
     */
    public function findByAptitudePrincipale(array $aptitudes): array
    {
        if (!$aptitudes) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('u.aptitude IN (:aptitudes)')->setParameter('aptitudes', $aptitudes)
            ->orderBy('u.nom', 'ASC')->addOrderBy('u.prenom', 'ASC')
            ->getQuery()->getResult();
    }

    /**
     * Destinataires d'une communication : les membres actifs (activation terminée, adresse mail renseignée).
     * Un filtre vide ne restreint pas ; plusieurs rôles ou plusieurs aptitudes s'additionnent, et rôles et aptitudes se combinent (ET).
     *
     * @param Role[]     $roles
     * @param Aptitude[] $aptitudes Aptitude principale du membre
     *
     * @return User[]
     */
    public function findPourCommunication(array $roles = [], array $aptitudes = []): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.activationTokenHash IS NULL')
            ->andWhere("u.email IS NOT NULL AND u.email <> ''")
            ->orderBy('u.nom', 'ASC')->addOrderBy('u.prenom', 'ASC');

        if ($roles) {
            $qb->andWhere('EXISTS (SELECT 1 FROM App\Entity\User u2 JOIN u2.userRoles r WHERE u2 = u AND r IN (:roles))')
                ->setParameter('roles', $roles);
        }
        if ($aptitudes) {
            $qb->andWhere('u.aptitude IN (:aptitudes)')->setParameter('aptitudes', $aptitudes);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return User[] */
    public function search(?string $term): array
    {
        $qb = $this->createQueryBuilder('u')
            ->addSelect('a')
            ->leftJoin('u.aptitude', 'a')
            ->orderBy('u.nom', 'ASC')
            ->addOrderBy('u.prenom', 'ASC');

        if ($term) {
            $qb->andWhere('u.nom LIKE :t OR u.prenom LIKE :t')
                ->setParameter('t', '%'.$term.'%');
        }

        return $qb->getQuery()->getResult();
    }
}
