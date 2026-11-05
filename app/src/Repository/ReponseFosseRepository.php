<?php

namespace App\Repository;

use App\Entity\InvitationFosse;
use App\Entity\ReponseFosse;
use App\Enum\StatutReponseFosse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ReponseFosse> */
class ReponseFosseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReponseFosse::class);
    }

    public function findOneByToken(string $token): ?ReponseFosse
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.invitation', 'i')->addSelect('i')
            ->leftJoin('r.user', 'u')->addSelect('u')
            ->where('r.token = :token')->setParameter('token', $token)
            ->getQuery()->getOneOrNullResult();
    }

    public function compter(InvitationFosse $invitation, StatutReponseFosse $statut): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.invitation = :invitation')->andWhere('r.statut = :statut')
            ->setParameter('invitation', $invitation)->setParameter('statut', $statut)
            ->getQuery()->getSingleScalarResult();
    }

    /** Premier arrivé de la liste d'attente (ordre de réponse, puis d'identifiant). */
    public function premierEnListeAttente(InvitationFosse $invitation): ?ReponseFosse
    {
        return $this->createQueryBuilder('r')
            ->where('r.invitation = :invitation')->andWhere('r.statut = :statut')
            ->setParameter('invitation', $invitation)->setParameter('statut', StatutReponseFosse::ListeAttente)
            ->orderBy('r.repondueAt', 'ASC')->addOrderBy('r.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();
    }
}
