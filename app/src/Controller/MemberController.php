<?php

namespace App\Controller;

use App\Repository\ActiviteExterneRepository;
use App\Repository\PlanningCreneauRepository;
use App\Entity\PlanningCreneau;
use App\Entity\User;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MemberController extends AbstractController
{
    #[Route('/membre', name: 'app_member')]
    public function index(ActiviteExterneRepository $activitesExternes, PlanningCreneauRepository $creneaux): Response
    {
        // Créneaux à venir où un moniteur du groupe est absent, pour les autres moniteurs du groupe
        $moi = $this->getUser();
        $besoinAide = [];
        if ($moi instanceof User && $this->isGranted(Permission::PLANNING_GROUPES)) {
            $besoinAide = array_values(array_filter(
                $creneaux->findAvecAbsencesAVenir(new \DateTimeImmutable('today')),
                static fn (PlanningCreneau $c) => $moi->encadreUneAptitudeDe($c) && null === $c->absenceDe($moi),
            ));
        }

        return $this->render('member/index.html.twig', [
            'besoinAide' => $besoinAide,
            'activitesExternes' => $activitesExternes->findBy([], ['libelle' => 'ASC']),
        ]);
    }
}
