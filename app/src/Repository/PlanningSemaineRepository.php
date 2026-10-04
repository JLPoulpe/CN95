<?php

namespace App\Repository;

use App\Entity\PlanningSemaine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<PlanningSemaine> */
class PlanningSemaineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PlanningSemaine::class);
    }

    public function findByLundi(\DateTimeImmutable $lundi): ?PlanningSemaine
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.creneaux', 'c')->addSelect('c')
            ->leftJoin('c.aptitudes', 'a')->addSelect('a')
            ->leftJoin('s.dp', 'dp')->addSelect('dp')
            ->where('s.lundi = :lundi')->setParameter('lundi', $lundi->format('Y-m-d'))
            ->getQuery()->getOneOrNullResult();
    }

    /** @return list<PlanningSemaine> */
    public function findAllDesc(): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.dp', 'dp')->addSelect('dp')
            ->orderBy('s.lundi', 'DESC')
            ->getQuery()->getResult();
    }
}
