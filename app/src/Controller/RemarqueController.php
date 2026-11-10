<?php

namespace App\Controller;

use App\Entity\Remarque;
use App\Entity\User;
use App\Form\RemarqueType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Dépôt d'une remarque : accessible à tout membre connecté (voir security.yaml, /membre). */
#[Route('/membre/remarque', name: 'app_remarque', methods: ['GET', 'POST'])]
class RemarqueController extends AbstractController
{
    public function __invoke(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $remarque = (new Remarque())->setAuteur($user);
        $form = $this->createForm(RemarqueType::class, $remarque);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $remarque->setContenu(trim($remarque->getContenu()));
            $em->persist($remarque);
            $em->flush();
            $this->addFlash('success', 'Merci, votre remarque a bien été enregistrée.');

            return $this->redirectToRoute('app_member');
        }

        return $this->render('remarque/form.html.twig', ['form' => $form]);
    }
}
