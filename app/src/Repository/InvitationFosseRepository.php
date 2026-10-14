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
     * @param list<string>|null $codesRoles Ne garder que les invitations partageant un de ces rôles ; null = toutes (administrateur)
     *
     * @return list<InvitationFosse>
     */
    public function findAffichables(\DateTimeImmutable $lundi, ?ActiviteExterne $activite = null, ?array $codesRoles = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.dp', 'dp')->addSelect('dp')
            ->where('i.date >= :lundi')->setParameter('lundi', $lundi->format('Y-m-d'));

        if (null !== $activite) {
            $qb->andWhere('i.activite = :activite')->setParameter('activite', $activite);
        }

        if (null !== $codesRoles) {
            if ([] === $codesRoles) {
                return [];
            }
            // Une invitation partagée avec plusieurs rôles du membre ne doit apparaître qu'une fois
            $qb->innerJoin('i.roles', 'role', 'WITH', 'role.code IN (:codesRoles)')
                ->setParameter('codesRoles', $codesRoles)
                ->distinct();
        }

        return $qb->orderBy('i.date', 'ASC')->getQuery()->getResult();
    }
}
