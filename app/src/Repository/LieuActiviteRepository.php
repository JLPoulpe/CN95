<?php

namespace App\Repository;

use App\Entity\LieuActivite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LieuActivite>
 */
class LieuActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LieuActivite::class);
    }

    /** @return list<LieuActivite> */
    public function findAllOrdonnes(): array
    {
        return $this->createQueryBuilder('l')
            ->orderBy('l.ordre', 'ASC')
            ->addOrderBy('l.groupe', 'ASC')
            ->addOrderBy('l.nom', 'ASC')
            ->getQuery()->getResult();
    }

    /** @return list<string> */
    public function findGroupes(): array
    {
        return array_column($this->createQueryBuilder('l')
            ->select('DISTINCT l.groupe')
            ->orderBy('l.groupe', 'ASC')
            ->getQuery()->getScalarResult(), 'groupe');
    }

    public function countUsages(LieuActivite $lieu): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(c.id) FROM App\Entity\PlanningCreneau c JOIN c.lieux l WHERE l = :l')
            ->setParameter('l', $lieu)
            ->getSingleScalarResult();
    }
}
