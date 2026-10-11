<?php

namespace App\Repository;

use App\Entity\ActiviteExterne;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ActiviteExterne> */
class ActiviteExterneRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActiviteExterne::class);
    }

    public function countUsages(ActiviteExterne $activite): int
    {
        return (int) $this->getEntityManager()
            ->createQuery('SELECT COUNT(i.id) FROM App\Entity\InvitationFosse i WHERE i.activite = :a')
            ->setParameter('a', $activite)
            ->getSingleScalarResult();
    }
}
