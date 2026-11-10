<?php

namespace App\Controller\Admin;

use App\Entity\Remarque;
use App\Repository\RemarqueRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Remarques des membres. Réservé au rôle administrateur (règle ^/admin de security.yaml). */
#[Route('/admin/remarques')]
class RemarqueController extends AbstractController
{
    #[Route('', name: 'admin_remarque_index')]
    public function index(RemarqueRepository $remarques): Response
    {
        return $this->render('admin/remarque/index.html.twig', ['remarques' => $remarques->findRecentes()]);
    }

    #[Route('/{id}', name: 'admin_remarque_show', requirements: ['id' => '\\d+'])]
    public function show(Remarque $remarque): Response
    {
        return $this->render('admin/remarque/show.html.twig', ['remarque' => $remarque]);
    }

    #[Route('/{id}/supprimer', name: 'admin_remarque_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Remarque $remarque, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('remarque_'.$remarque->getId(), $request->request->getString('_token'))) {
            $em->remove($remarque);
            $em->flush();
            $this->addFlash('success', 'Remarque supprimée.');
        }

        return $this->redirectToRoute('admin_remarque_index');
    }
}
