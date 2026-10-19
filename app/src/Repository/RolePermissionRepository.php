<?php

namespace App\Repository;

use App\Entity\RolePermission;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<RolePermission> */
class RolePermissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RolePermission::class);
    }

    /** @return array<string, list<string>> Codes de rôles accordant chaque permission */
    public function rolesParPermission(): array
    {
        $lignes = $this->createQueryBuilder('rp')
            ->select('rp.permission AS permission', 'r.code AS code')
            ->join('rp.role', 'r')
            ->getQuery()->getArrayResult();

        $resultat = [];
        foreach ($lignes as $ligne) {
            $resultat[$ligne['permission']][] = $ligne['code'];
        }

        return $resultat;
    }
}
