<?php

namespace App\Repository;

use App\Entity\FicheSecurite;
use App\Entity\InvitationFosse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<FicheSecurite> */
class FicheSecuriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FicheSecurite::class);
    }

    public function findOneByInvitation(InvitationFosse $invitation): ?FicheSecurite
    {
        return $this->findOneBy(['invitation' => $invitation]);
    }
}
