<?php

namespace App\Repository;

use App\Entity\Activite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activite>
 */
class ActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    /**
     * Nombre de créneaux de planning référençant cette activité.
     */
    public function countUsages(Activite $activite): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(c.id) FROM App\Entity\PlanningCreneau c JOIN c.activites a WHERE a = :a')
            ->setParameter('a', $activite)
            ->getSingleScalarResult();
    }
}
