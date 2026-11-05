<?php

namespace App\Controller\Admin;

use App\Entity\Role;
use App\Entity\User;
use App\Form\MoniteurGroupesType;
use App\Repository\AptitudeRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users/moniteurs')]
class MoniteurController extends AbstractController
{
    #[Route('', name: 'admin_moniteur_index')]
    public function index(UserRepository $users, AptitudeRepository $aptitudes): Response
    {
        $moniteurs = $users->findMoniteurs();

        $groupes = [];
        foreach ($aptitudes->findBy([], ['ordre' => 'ASC', 'code' => 'ASC']) as $aptitude) {
            $groupes[] = [
                'aptitude' => $aptitude,
                'moniteurs' => array_filter($moniteurs, static fn (User $m) => $m->getAptitudesEncadrees()->contains($aptitude)),
            ];
        }

        return $this->render('admin/moniteur/index.html.twig', ['groupes' => $groupes, 'moniteurs' => $moniteurs]);
    }

    #[Route('/{id}/edit', name: 'admin_moniteur_edit', requirements: ['id' => '\\d+'])]
    public function edit(User $moniteur, Request $request, EntityManagerInterface $em): Response
    {
        if (!$moniteur->hasRole(Role::MONITEUR)) {
            throw $this->createNotFoundException('Cet utilisateur n\'est pas moniteur.');
        }

        $form = $this->createForm(MoniteurGroupesType::class, $moniteur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Groupes du moniteur modifiés.');

            return $this->redirectToRoute('admin_moniteur_index');
        }

        return $this->render('admin/moniteur/edit.html.twig', ['form' => $form, 'moniteur' => $moniteur]);
    }
}
