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

    /**
     * Créneaux à venir (aujourd'hui compris) ayant au moins une absence de moniteur, dans l'ordre chronologique.
     *
     * @return list<PlanningCreneau>
     */
    public function findAvecAbsencesAVenir(\DateTimeImmutable $aujourdhui): array
    {
        $lundi = $aujourdhui->modify('monday this week')->setTime(0, 0);
        $creneaux = $this->createQueryBuilder('c')
            ->join('c.absences', 'ab')->addSelect('ab')
            ->join('c.semaine', 's')->addSelect('s')
            ->where('s.lundi >= :lundi')->setParameter('lundi', $lundi)
            ->getQuery()->getResult();

        $creneaux = array_values(array_filter($creneaux, static fn (PlanningCreneau $c) => $c->getDate() >= $aujourdhui->setTime(0, 0)));
        usort($creneaux, static fn (PlanningCreneau $a, PlanningCreneau $b) => $a->getDate() <=> $b->getDate());

        return $creneaux;
    }
}
