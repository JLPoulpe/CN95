<?php

namespace App\Repository;

use App\Entity\Activite;
use App\Entity\PlanningCreneau;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PlanningCreneau> */
class PlanningCreneauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanningCreneau::class);
    }

    /**
     * Tous les créneaux d'une activité, toutes semaines confondues, du plus récent au plus ancien.
     *
     * @return list<PlanningCreneau>
     */
    public function findByActivite(Activite $activite): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.activites', 'a')->andWhere('a = :a')->setParameter('a', $activite)
            ->join('c.semaine', 's')->addSelect('s')
            ->orderBy('s.lundi', 'DESC')
            ->getQuery()->getResult();
    }
}
