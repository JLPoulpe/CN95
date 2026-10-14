<?php

namespace App\Controller;

use App\Planning\VuePlanning;
use App\Repository\AptitudeRepository;
use App\Repository\PlanningSemaineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(PlanningSemaineRepository $semaines, AptitudeRepository $aptitudes, VuePlanning $vue): Response
    {
        // Semaine en cours, sans filtre préselectionné : le visiteur choisit l'aptitude qui l'intéresse
        $lundi = PlanningController::lundiDe(new \DateTimeImmutable('today'));
        $semaine = $semaines->findByLundi($lundi);

        return $this->render('home/index.html.twig', [
            'lundi' => $lundi,
            'semaine' => $semaine,
            'jours' => $vue->jours($semaine, $lundi),
            'aptitudes' => $aptitudes->findBy([], ['ordre' => 'ASC']),
        ]);
    }
}
