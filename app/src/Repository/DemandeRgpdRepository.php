<?php

namespace App\Repository;

use App\Entity\DemandeRgpd;
use App\Entity\User;
use App\Enum\TypeDemandeRgpd;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DemandeRgpd> */
class DemandeRgpdRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DemandeRgpd::class);
    }

    /** Le jeton clair est haché avant la recherche, comme pour l'activation de compte. */
    public function findOneByJeton(string $jeton): ?DemandeRgpd
    {
        return $this->findOneBy(['tokenHash' => hash('sha256', $jeton)]);
    }

    /** Une seule demande par membre, type et version : évite de renvoyer deux fois la même information. */
    public function existePourVersion(User $user, TypeDemandeRgpd $type, string $version): bool
    {
        return null !== $this->findOneBy(['user' => $user, 'type' => $type, 'version' => $version]);
    }
}
