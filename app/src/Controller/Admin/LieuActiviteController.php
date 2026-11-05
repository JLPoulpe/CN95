<?php

namespace App\Controller\Admin;

use App\Entity\LieuActivite;
use App\Form\LieuActiviteType;
use App\Lieu\LieuActiviteCsvImporter;
use App\Repository\LieuActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/lieux-activite')]
class LieuActiviteController extends AbstractController
{
    #[Route('', name: 'admin_lieu_activite_index')]
    public function index(LieuActiviteRepository $lieux): Response
    {
        return $this->render('admin/lieu_activite/index.html.twig', ['lieux' => $lieux->findAllOrdonnes()]);
    }

    #[Route('/import', name: 'admin_lieu_activite_import', methods: ['POST'])]
    public function import(Request $request, LieuActiviteCsvImporter $importer): Response
    {
        if (!$this->isCsrfTokenValid('import_lieux_activite', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');

            return $this->redirectToRoute('admin_lieu_activite_index');
        }
        $file = $request->files->get('fichier');
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            $this->addFlash('error', 'Veuillez sélectionner un fichier CSV.');

            return $this->redirectToRoute('admin_lieu_activite_index');
        }

        $result = $importer->import($file->getPathname());
        if ($result['created'] + $result['updated'] > 0) {
            $this->addFlash('success', sprintf('Import terminé : %d créé(s), %d mis à jour.', $result['created'], $result['updated']));
        }
        foreach (array_slice($result['errors'], 0, 20) as $error) {
            $this->addFlash('error', $error);
        }

        return $this->redirectToRoute('admin_lieu_activite_index');
    }

    #[Route('/import/modele', name: 'admin_lieu_activite_import_model')]
    public function importModel(): Response
    {
        $csv = "\xEF\xBB\xBFgroupe;nom;ordre\nPetit bassin;Ligne 1;11\nPetit bassin;Ligne 2;12\nSalles;Grande salle;100\n";

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele_lieux_activite.csv"',
        ]);
    }

    #[Route('/new', name: 'admin_lieu_activite_new')]
    public function new(Request $request, EntityManagerInterface $em, LieuActiviteRepository $lieux): Response
    {
        $lieu = new LieuActivite();
        $form = $this->createForm(LieuActiviteType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->doublonExiste($lieux, $lieu)) {
                $this->erreurDoublon($form);
            } else {
                $em->persist($lieu);
                $em->flush();
                $this->addFlash('success', 'Lieu d\'activité créé.');

                return $this->redirectToRoute('admin_lieu_activite_index');
            }
        }

        return $this->render('admin/lieu_activite/new.html.twig', ['form' => $form, 'groupes' => $lieux->findGroupes()]);
    }

    #[Route('/{id}/edit', name: 'admin_lieu_activite_edit', requirements: ['id' => '\\d+'])]
    public function edit(LieuActivite $lieu, Request $request, EntityManagerInterface $em, LieuActiviteRepository $lieux): Response
    {
        $form = $this->createForm(LieuActiviteType::class, $lieu);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->doublonExiste($lieux, $lieu)) {
                $this->erreurDoublon($form);
            } else {
                $em->flush();
                $this->addFlash('success', 'Lieu d\'activité modifié.');

                return $this->redirectToRoute('admin_lieu_activite_index');
            }
        }

        return $this->render('admin/lieu_activite/edit.html.twig', ['form' => $form, 'lieu' => $lieu, 'groupes' => $lieux->findGroupes()]);
    }

    #[Route('/{id}/supprimer', name: 'admin_lieu_activite_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(LieuActivite $lieu, Request $request, EntityManagerInterface $em, LieuActiviteRepository $lieux): Response
    {
        if ($this->isCsrfTokenValid('delete_lieu_activite_'.$lieu->getId(), $request->request->getString('_token'))) {
            $usage = $lieux->countUsages($lieu);
            if ($usage > 0) {
                $this->addFlash('error', sprintf('Impossible de supprimer « %s » : il est utilisé (%d référence(s)).', $lieu, $usage));
            } else {
                $em->remove($lieu);
                $em->flush();
                $this->addFlash('success', 'Lieu d\'activité supprimé.');
            }
        }

        return $this->redirectToRoute('admin_lieu_activite_index');
    }

    private function doublonExiste(LieuActiviteRepository $lieux, LieuActivite $lieu): bool
    {
        $other = $lieux->findOneBy(['groupe' => $lieu->getGroupe(), 'nom' => $lieu->getNom()]);

        return null !== $other && $other->getId() !== $lieu->getId();
    }

    private function erreurDoublon(FormInterface $form): void
    {
        $form->get('nom')->addError(new FormError('Ce lieu existe déjà dans ce groupe.'));
    }
}
