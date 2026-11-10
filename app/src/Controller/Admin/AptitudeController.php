<?php

namespace App\Controller\Admin;

use App\Entity\Aptitude;
use App\Form\AptitudeType;
use App\Repository\AptitudeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/aptitudes')]
class AptitudeController extends AbstractController
{
    #[Route('', name: 'admin_aptitude_index')]
    public function index(AptitudeRepository $aptitudes): Response
    {
        return $this->render('admin/aptitude/index.html.twig', [
            'aptitudes' => $aptitudes->findBy([], ['ordre' => 'ASC', 'code' => 'ASC']),
        ]);
    }

    #[Route('/new', name: 'admin_aptitude_new')]
    public function new(Request $request, EntityManagerInterface $em, AptitudeRepository $aptitudes): Response
    {
        $aptitude = new Aptitude();
        $aptitude->setOrdre($aptitudes->count([]) + 1);
        $form = $this->createForm(AptitudeType::class, $aptitude);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->codeExists($aptitudes, $aptitude)) {
                $form->get('code')->addError(new \Symfony\Component\Form\FormError('Ce code existe déjà.'));
            } else {
                $em->persist($aptitude);
                $em->flush();
                $this->addFlash('success', 'Aptitude créée.');

                return $this->redirectToRoute('admin_aptitude_index');
            }
        }

        return $this->render('admin/aptitude/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_aptitude_edit', requirements: ['id' => '\\d+'])]
    public function edit(Aptitude $aptitude, Request $request, EntityManagerInterface $em, AptitudeRepository $aptitudes): Response
    {
        $form = $this->createForm(AptitudeType::class, $aptitude);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->codeExists($aptitudes, $aptitude)) {
                $form->get('code')->addError(new \Symfony\Component\Form\FormError('Ce code existe déjà.'));
            } else {
                $em->flush();
                $this->addFlash('success', 'Aptitude modifiée.');

                return $this->redirectToRoute('admin_aptitude_index');
            }
        }

        return $this->render('admin/aptitude/edit.html.twig', ['form' => $form, 'aptitude' => $aptitude]);
    }

    #[Route('/{id}/supprimer', name: 'admin_aptitude_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Aptitude $aptitude, Request $request, EntityManagerInterface $em, AptitudeRepository $aptitudes): Response
    {
        if ($this->isCsrfTokenValid('delete_aptitude_'.$aptitude->getId(), $request->request->getString('_token'))) {
            $usage = $aptitudes->countUsages($aptitude);
            if ($usage > 0) {
                $this->addFlash('error', sprintf('Impossible de supprimer « %s » : elle est utilisée (%d référence(s)).', $aptitude, $usage));
            } else {
                $em->remove($aptitude);
                $em->flush();
                $this->addFlash('success', 'Aptitude supprimée.');
            }
        }

        return $this->redirectToRoute('admin_aptitude_index');
    }

    private function codeExists(AptitudeRepository $aptitudes, Aptitude $aptitude): bool
    {
        $other = $aptitudes->findOneBy(['code' => $aptitude->getCode()]);

        return $other !== null && $other->getId() !== $aptitude->getId();
    }
}
