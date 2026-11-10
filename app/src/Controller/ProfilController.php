<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfilType;
use App\Rgpd\PurgeAnnuelle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Espace « Mes données personnelles » : accessible à tout membre connecté, pour sa propre fiche uniquement. */
#[Route('/membre/profil')]
class ProfilController extends AbstractController
{
    #[Route('', name: 'app_profil', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        $form = $this->createForm(ProfilType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Vos données personnelles ont été enregistrées.');

            return $this->redirectToRoute('app_member');
        }

        return $this->render('profil/index.html.twig', [
            'form' => $form,
            // Arrivée juste après l'activation du compte : on demande de vérifier les informations et le niveau
            'bienvenue' => $request->query->getBoolean('bienvenue'),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/supprimer', name: 'app_profil_supprimer', methods: ['POST'])]
    public function supprimer(Request $request, PurgeAnnuelle $purge, Security $security): Response
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        if (!$this->isCsrfTokenValid('profil_supprimer', $request->request->getString('_token'))) {
            $this->addFlash('error', 'Session expirée, merci de réessayer.');

            return $this->redirectToRoute('app_profil');
        }

        try {
            $purge->supprimerUtilisateur((int) $user->getId());
        } catch (\DomainException) {
            $this->addFlash('error', 'Vous êtes le dernier administrateur : désignez-en un autre avant de supprimer votre compte.');

            return $this->redirectToRoute('app_profil');
        }

        // Le compte n'existe plus : on ferme la session (le flash est ajouté ensuite, dans la nouvelle session)
        $security->logout(false);
        $this->addFlash('success', 'Vos données personnelles ont été supprimées. Vous ne recevrez plus d\'invitation et n\'avez plus accès à l\'outil.');

        return $this->redirectToRoute('app_login');
    }
}
