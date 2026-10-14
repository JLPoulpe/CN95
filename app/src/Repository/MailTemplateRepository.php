<?php

namespace App\Repository;

use App\Entity\MailTemplate;
use App\Enum\TypeMailTemplate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<MailTemplate> */
class MailTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MailTemplate::class);
    }

    /** @return list<MailTemplate> */
    public function findParType(TypeMailTemplate $type): array
    {
        return $this->findBy(['type' => $type], ['nom' => 'ASC']);
    }

    public function parDefaut(TypeMailTemplate $type): ?MailTemplate
    {
        return $this->findOneBy(['type' => $type, 'parDefaut' => true]);
    }

    /** Retire le statut « par défaut » des autres templates du même type. */
    public function definirParDefaut(MailTemplate $template): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.parDefaut', ':faux')
            ->where('m.type = :type')->andWhere('m.id <> :id')
            ->setParameter('faux', false)
            ->setParameter('type', $template->getType())
            ->setParameter('id', $template->getId() ?? 0)
            ->getQuery()->execute();

        $template->setParDefaut(true);
    }
}
