<?php

namespace App\Controller\Admin;

use App\Entity\ActiviteExterne;
use App\Form\ActiviteExterneType;
use App\Repository\ActiviteExterneRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/activites-externes')]
class ActiviteExterneController extends AbstractController
{
    #[Route('', name: 'admin_activite_externe_index')]
    public function index(ActiviteExterneRepository $activites): Response
    {
        return $this->render('admin/activite_externe/index.html.twig', [
            'activites' => $activites->findBy([], ['libelle' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_activite_externe_new')]
    public function new(Request $request, EntityManagerInterface $em, ActiviteExterneRepository $activites): Response
    {
        $activite = new ActiviteExterne();
        $form = $this->createForm(ActiviteExterneType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->codeExists($activites, $activite)) {
                $form->get('code')->addError(new FormError('Ce code existe déjà.'));
            } else {
                $em->persist($activite);
                $em->flush();
                $this->addFlash('success', 'Activité externe créée.');

                return $this->redirectToRoute('admin_activite_externe_index');
            }
        }

        return $this->render('admin/activite_externe/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_activite_externe_edit', requirements: ['id' => '\\d+'])]
    public function edit(ActiviteExterne $activite, Request $request, EntityManagerInterface $em, ActiviteExterneRepository $activites): Response
    {
        $form = $this->createForm(ActiviteExterneType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->codeExists($activites, $activite)) {
                $form->get('code')->addError(new FormError('Ce code existe déjà.'));
            } else {
                $em->flush();
                $this->addFlash('success', 'Activité externe modifiée.');

                return $this->redirectToRoute('admin_activite_externe_index');
            }
        }

        return $this->render('admin/activite_externe/edit.html.twig', ['form' => $form, 'activite' => $activite]);
    }

    #[Route('/{id}/supprimer', name: 'admin_activite_externe_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(ActiviteExterne $activite, Request $request, EntityManagerInterface $em, ActiviteExterneRepository $activites): Response
    {
        if ($this->isCsrfTokenValid('delete_activite_externe_'.$activite->getId(), $request->request->getString('_token'))) {
            $usage = $activites->countUsages($activite);
            if ($usage > 0) {
                $this->addFlash('error', sprintf('Impossible de supprimer « %s » : elle est utilisée (%d invitation(s)).', $activite, $usage));
            } else {
                $em->remove($activite);
                $em->flush();
                $this->addFlash('success', 'Activité externe supprimée.');
            }
        }

        return $this->redirectToRoute('admin_activite_externe_index');
    }

    private function codeExists(ActiviteExterneRepository $activites, ActiviteExterne $activite): bool
    {
        $other = $activites->findOneBy(['code' => $activite->getCode()]);

        return null !== $other && $other->getId() !== $activite->getId();
    }
}
