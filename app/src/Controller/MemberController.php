<?php

namespace App\Controller;

use App\Repository\ActiviteExterneRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MemberController extends AbstractController
{
    #[Route('/membre', name: 'app_member')]
    public function index(ActiviteExterneRepository $activitesExternes): Response
    {
        return $this->render('member/index.html.twig', [
            'activitesExternes' => $activitesExternes->findBy([], ['libelle' => 'ASC']),
        ]);
    }
}
