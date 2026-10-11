<?php

namespace App\Repository;

use App\Entity\LigneEau;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LigneEau>
 */
class LigneEauRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneEau::class);
    }

    /** @return list<LigneEau> */
    public function findAllGroupedByLieu(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.lieu', 'ASC')
            ->addOrderBy('l.numero', 'ASC')
            ->getQuery()->getResult();
    }

    public function countUsages(LigneEau $ligne): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(c.id) FROM App\Entity\PlanningCreneau c JOIN c.lignes l WHERE l = :l')
            ->setParameter('l', $ligne)
            ->getSingleScalarResult();
    }
}
