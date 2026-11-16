<?php

namespace App\Repository;

use App\Entity\AbsenceMoniteur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AbsenceMoniteur> */
class AbsenceMoniteurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AbsenceMoniteur::class);
    }
}
