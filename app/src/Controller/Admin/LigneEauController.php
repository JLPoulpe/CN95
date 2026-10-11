<?php

namespace App\Controller\Admin;

use App\Entity\LigneEau;
use App\Form\LigneEauType;
use App\Repository\LigneEauRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lignes-eau')]
class LigneEauController extends AbstractController
{
    #[Route('', name: 'admin_ligne_eau_index')]
    public function index(LigneEauRepository $lignes): Response
    {
        return $this->render('admin/ligne_eau/index.html.twig', [
            'lignes' => $lignes->findAllGroupedByLieu(),
        ]);
    }

    #[Route('/new', name: 'admin_ligne_eau_new')]
    public function new(Request $request, EntityManagerInterface $em, LigneEauRepository $lignes): Response
    {
        $ligne = new LigneEau();
        $form = $this->createForm(LigneEauType::class, $ligne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->doublonExiste($lignes, $ligne)) {
                $form->get('numero')->addError(new FormError('Cette ligne existe déjà pour ce lieu.'));
            } else {
                $em->persist($ligne);
                $em->flush();
                $this->addFlash('success', 'Ligne d\'eau créée.');

                return $this->redirectToRoute('admin_ligne_eau_index');
            }
        }

        return $this->render('admin/ligne_eau/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_ligne_eau_edit', requirements: ['id' => '\\d+'])]
    public function edit(LigneEau $ligne, Request $request, EntityManagerInterface $em, LigneEauRepository $lignes): Response
    {
        $form = $this->createForm(LigneEauType::class, $ligne);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->doublonExiste($lignes, $ligne)) {
                $form->get('numero')->addError(new FormError('Cette ligne existe déjà pour ce lieu.'));
            } else {
                $em->flush();
                $this->addFlash('success', 'Ligne d\'eau modifiée.');

                return $this->redirectToRoute('admin_ligne_eau_index');
            }
        }

        return $this->render('admin/ligne_eau/edit.html.twig', ['form' => $form, 'ligne' => $ligne]);
    }

    #[Route('/{id}/supprimer', name: 'admin_ligne_eau_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(LigneEau $ligne, Request $request, EntityManagerInterface $em, LigneEauRepository $lignes): Response
    {
        if ($this->isCsrfTokenValid('delete_ligne_eau_'.$ligne->getId(), $request->request->getString('_token'))) {
            $usage = $lignes->countUsages($ligne);
            if ($usage > 0) {
                $this->addFlash('error', sprintf('Impossible de supprimer « %s » : elle est utilisée (%d référence(s)).', $ligne, $usage));
            } else {
                $em->remove($ligne);
                $em->flush();
                $this->addFlash('success', 'Ligne d\'eau supprimée.');
            }
        }

        return $this->redirectToRoute('admin_ligne_eau_index');
    }

    private function doublonExiste(LigneEauRepository $lignes, LigneEau $ligne): bool
    {
        $other = $lignes->findOneBy(['lieu' => $ligne->getLieu(), 'numero' => $ligne->getNumero()]);

        return null !== $other && $other->getId() !== $ligne->getId();
    }
}
