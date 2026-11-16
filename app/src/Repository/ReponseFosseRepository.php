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

    /**
     * Liste d'attente dans l'ordre de promotion (ordre manuel, puis ordre d'arrivée).
     *
     * @return list<ReponseFosse>
     */
    public function listeAttente(InvitationFosse $invitation): array
    {
        $reponses = $this->findBy(['invitation' => $invitation, 'statut' => StatutReponseFosse::ListeAttente]);
        usort($reponses, ReponseFosse::comparerAttente(...));

        return $reponses;
    }

    /** Prochain à promouvoir depuis la liste d'attente. */
    public function premierEnListeAttente(InvitationFosse $invitation): ?ReponseFosse
    {
        return $this->listeAttente($invitation)[0] ?? null;
    }
}
