<?php

namespace App\Repository;

use App\Entity\Aptitude;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Aptitude>
 */
class AptitudeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Aptitude::class);
    }

    /**
     * Nombre de références (utilisateurs et créneaux) vers cette aptitude.
     */
    public function countUsages(Aptitude $aptitude): int
    {
        $em = $this->getEntityManager();

        $users = (int) $em->createQuery('SELECT COUNT(u.id) FROM App\Entity\User u WHERE u.aptitude = :a OR u.aptitudePreparee = :a')
            ->setParameter('a', $aptitude)->getSingleScalarResult();
        $creneaux = (int) $em->createQuery('SELECT COUNT(c.id) FROM App\Entity\PlanningCreneau c JOIN c.aptitudes a WHERE a = :a')
            ->setParameter('a', $aptitude)->getSingleScalarResult();

        return $users + $creneaux;
    }
}
