<?php

namespace App\Repository;

use App\Entity\ActiviteExterne;
use App\Entity\InvitationFosse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<InvitationFosse> */
class InvitationFosseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InvitationFosse::class);
    }

    /**
     * Invitations de la semaine en cours et à venir, éventuellement filtrées par activité.
     *
     * @return list<InvitationFosse>
     */
    public function findAffichables(\DateTimeImmutable $lundi, ?ActiviteExterne $activite = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.dp', 'dp')->addSelect('dp')
            ->where('i.date >= :lundi')->setParameter('lundi', $lundi->format('Y-m-d'));

        if (null !== $activite) {
            $qb->andWhere('i.activite = :activite')->setParameter('activite', $activite);
        }

        return $qb->orderBy('i.date', 'ASC')->getQuery()->getResult();
    }
}
