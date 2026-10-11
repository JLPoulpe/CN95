<?php

namespace App\Repository;

use App\Entity\ReponseFosse;
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
}
