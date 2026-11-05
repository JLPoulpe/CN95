<?php

namespace App\Controller\Admin;

use App\Aptitude\AptitudeCsvImporter;
use App\Entity\Aptitude;
use App\Form\AptitudeType;
use App\Repository\AptitudeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

    #[Route('/import', name: 'admin_aptitude_import', methods: ['POST'])]
    public function import(Request $request, AptitudeCsvImporter $importer): Response
    {
        if (!$this->isCsrfTokenValid('import_aptitudes', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_aptitude_index');
        }
        $file = $request->files->get('fichier');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier CSV.');

            return $this->redirectToRoute('admin_aptitude_index');
        }

        $result = $importer->import($file->getPathname());
        if ($result['created'] + $result['updated'] > 0) {
            $this->addFlash('success', sprintf('Import terminé : %d créée(s), %d mise(s) à jour.', $result['created'], $result['updated']));
        }
        foreach (array_slice($result['errors'], 0, 20) as $error) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('admin_aptitude_index');
    }

    #[Route('/import/modele', name: 'admin_aptitude_import_model')]
    public function importModel(): Response
    {
        $csv = "\xEF\xBB\xBFcode;libelle;ordre\nE2;Encadrant niveau 2;1\nPE40;Plongeur Encadré 40 m;2\n";

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele_aptitudes.csv"',
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
