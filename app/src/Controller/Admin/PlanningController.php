<?php

namespace App\Controller\Admin;

use App\Controller\PlanningController as PublicPlanningController;
use App\Entity\PlanningCreneau;
use App\Entity\PlanningSemaine;
use App\Form\PlanningCreneauType;
use App\Form\PlanningDuplicationType;
use App\Form\PlanningSemaineType;
use App\Repository\PlanningSemaineRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/planning')]
class PlanningController extends AbstractController
{
    #[Route('', name: 'admin_planning_index')]
    public function index(PlanningSemaineRepository $semaines): Response
    {
        return $this->render('admin/planning/index.html.twig', [
            'semaines' => $semaines->findAllDesc(),
            'courante' => PublicPlanningController::lundiDe(new \DateTimeImmutable('today')),
        ]);
    }

    #[Route('/new', name: 'admin_planning_new')]
    public function new(Request $request, EntityManagerInterface $em, PlanningSemaineRepository $semaines): Response
    {
        $dernier = $semaines->findOneBy([], ['lundi' => 'DESC']);
        $semaine = (new PlanningSemaine())->setLundi(
            $dernier ? $dernier->getLundi()->modify('+1 week') : PublicPlanningController::lundiDe(new \DateTimeImmutable('today'))
        );
        $form = $this->createForm(PlanningSemaineType::class, $semaine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($semaine);
            $em->flush();
            $this->addFlash('success', 'Semaine créée. Ajoutez maintenant les créneaux.');

            return $this->redirectToRoute('admin_planning_edit', ['id' => $semaine->getId()]);
        }

        return $this->render('admin/planning/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_planning_edit', requirements: ['id' => '\\d+'])]
    public function edit(PlanningSemaine $semaine, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PlanningSemaineType::class, $semaine);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Semaine modifiée.');

            return $this->redirectToRoute('admin_planning_edit', ['id' => $semaine->getId()]);
        }

        return $this->render('admin/planning/edit.html.twig', ['form' => $form, 'semaine' => $semaine]);
    }

    #[Route('/{id}/supprimer', name: 'admin_planning_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(PlanningSemaine $semaine, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_semaine_'.$semaine->getId(), $request->request->getString('_token'))) {
            $em->remove($semaine);
            $em->flush();
            $this->addFlash('success', 'Semaine supprimée.');
        }

        return $this->redirectToRoute('admin_planning_index');
    }

    #[Route('/{id}/dupliquer', name: 'admin_planning_duplicate', requirements: ['id' => '\\d+'])]
    public function duplicate(PlanningSemaine $source, Request $request, EntityManagerInterface $em, PlanningSemaineRepository $semaines): Response
    {
        $form = $this->createForm(PlanningDuplicationType::class, ['lundi' => $source->getLundi()->modify('+1 week')]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lundi = $form->get('lundi')->getData();
            if ($semaines->findOneBy(['lundi' => $lundi])) {
                $form->get('lundi')->addError(new \Symfony\Component\Form\FormError('Cette semaine existe déjà.'));
            } else {
                $copie = (new PlanningSemaine())->setLundi($lundi)->setFermee($source->isFermee())->setMotif($source->getMotif());
                foreach ($source->getCreneaux() as $creneau) {
                    $copie->addCreneau(clone $creneau);
                }
                $em->persist($copie);
                $em->flush();
                $this->addFlash('success', 'Semaine dupliquée (le DP n\'est pas repris, à choisir).');

                return $this->redirectToRoute('admin_planning_edit', ['id' => $copie->getId()]);
            }
        }

        return $this->render('admin/planning/duplicate.html.twig', ['form' => $form, 'source' => $source]);
    }

    #[Route('/{id}/creneau/new', name: 'admin_planning_creneau_new', requirements: ['id' => '\\d+'])]
    public function creneauNew(PlanningSemaine $semaine, Request $request, EntityManagerInterface $em): Response
    {
        $creneau = new PlanningCreneau();
        $form = $this->createForm(PlanningCreneauType::class, $creneau);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $semaine->addCreneau($creneau);
            $em->persist($creneau);
            $em->flush();
            $this->addFlash('success', 'Créneau ajouté.');

            return $this->redirectToRoute('admin_planning_edit', ['id' => $semaine->getId()]);
        }

        return $this->render('admin/planning/creneau.html.twig', ['form' => $form, 'semaine' => $semaine, 'titre' => 'Nouveau créneau']);
    }

    #[Route('/creneau/{id}/edit', name: 'admin_planning_creneau_edit', requirements: ['id' => '\\d+'])]
    public function creneauEdit(PlanningCreneau $creneau, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(PlanningCreneauType::class, $creneau);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Créneau modifié.');

            return $this->redirectToRoute('admin_planning_edit', ['id' => $creneau->getSemaine()->getId()]);
        }

        return $this->render('admin/planning/creneau.html.twig', ['form' => $form, 'semaine' => $creneau->getSemaine(), 'titre' => 'Modifier le créneau']);
    }

    #[Route('/creneau/{id}/supprimer', name: 'admin_planning_creneau_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function creneauDelete(PlanningCreneau $creneau, Request $request, EntityManagerInterface $em): Response
    {
        $semaineId = $creneau->getSemaine()->getId();
        if ($this->isCsrfTokenValid('delete_creneau_'.$creneau->getId(), $request->request->getString('_token'))) {
            $em->remove($creneau);
            $em->flush();
            $this->addFlash('success', 'Créneau supprimé.');
        }

        return $this->redirectToRoute('admin_planning_edit', ['id' => $semaineId]);
    }
}
