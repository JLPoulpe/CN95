<?php

namespace App\Controller\Admin;

use App\Entity\Activite;
use App\Form\ActiviteType;
use App\Repository\ActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/activites')]
class ActiviteController extends AbstractController
{
    #[Route('', name: 'admin_activite_index')]
    public function index(ActiviteRepository $activites): Response
    {
        return $this->render('admin/activite/index.html.twig', [
            'activites' => $activites->findBy([], ['ordre' => 'ASC', 'code' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_activite_new')]
    public function new(Request $request, EntityManagerInterface $em, ActiviteRepository $activites): Response
    {
        $activite = new Activite();
        $activite->setOrdre($activites->count([]) + 1);
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->codeExists($activites, $activite)) {
                $form->get('code')->addError(new FormError('Ce code existe déjà.'));
            } else {
                $em->persist($activite);
                $em->flush();
                $this->addFlash('success', 'Activité créée.');

                return $this->redirectToRoute('admin_activite_index');
            }
        }

        return $this->render('admin/activite/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_activite_edit', requirements: ['id' => '\\d+'])]
    public function edit(Activite $activite, Request $request, EntityManagerInterface $em, ActiviteRepository $activites): Response
    {
        $form = $this->createForm(ActiviteType::class, $activite);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->codeExists($activites, $activite)) {
                $form->get('code')->addError(new FormError('Ce code existe déjà.'));
            } else {
                $em->flush();
                $this->addFlash('success', 'Activité modifiée.');

                return $this->redirectToRoute('admin_activite_index');
            }
        }

        return $this->render('admin/activite/edit.html.twig', ['form' => $form, 'activite' => $activite]);
    }

    #[Route('/{id}/supprimer', name: 'admin_activite_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Activite $activite, Request $request, EntityManagerInterface $em, ActiviteRepository $activites): Response
    {
        if ($this->isCsrfTokenValid('delete_activite_'.$activite->getId(), $request->request->getString('_token'))) {
            $usage = $activites->countUsages($activite);
            if ($usage > 0) {
                $this->addFlash('error', sprintf('Impossible de supprimer « %s » : elle est utilisée (%d référence(s)).', $activite, $usage));
            } else {
                $em->remove($activite);
                $em->flush();
                $this->addFlash('success', 'Activité supprimée.');
            }
        }

        return $this->redirectToRoute('admin_activite_index');
    }

    private function codeExists(ActiviteRepository $activites, Activite $activite): bool
    {
        $other = $activites->findOneBy(['code' => $activite->getCode()]);

        return null !== $other && $other->getId() !== $activite->getId();
    }
}
