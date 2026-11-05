<?php

namespace App\Controller\Admin;

use App\Entity\FicheSecurite;
use App\Entity\InvitationFosse;
use App\Entity\User;
use App\Fosse\FicheSecuriteBuilder;
use App\Repository\FicheSecuriteRepository;
use App\Security\Voter\FicheSecuriteVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Fiche de sécurité (palanquées) d'une invitation : réservée au DP de l'invitation et aux administrateurs. */
#[Route('/admin/invitations/{id}/fiche-securite', requirements: ['id' => '\\d+'])]
class FicheSecuriteController extends AbstractController
{
    #[Route('', name: 'admin_fiche_securite_show')]
    public function show(InvitationFosse $invitation, FicheSecuriteRepository $fiches, FicheSecuriteBuilder $builder, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(FicheSecuriteVoter::OUVRIR, $invitation);

        // Première ouverture : les palanquées sont générées d'après les inscrits
        $fiche = $fiches->findOneByInvitation($invitation);
        if (null === $fiche) {
            $fiche = $builder->generer($invitation);
            $em->persist($fiche);
            $em->flush();
        }

        return $this->render('admin/fiche_securite/show.html.twig', [
            'invitation' => $invitation,
            'fiche' => $fiche,
            'restants' => $builder->restants($fiche),
            'nb_inscrits' => count($builder->inscrits($invitation)),
        ]);
    }

    #[Route('/palanquees', name: 'admin_fiche_securite_ajouter', methods: ['POST'])]
    public function ajouter(InvitationFosse $invitation, Request $request, FicheSecuriteRepository $fiches, EntityManagerInterface $em): Response
    {
        $fiche = $this->fiche($invitation, $request, $fiches);
        $fiche->ajouterPalanquee();
        $em->flush();

        return $this->redirectToRoute('admin_fiche_securite_show', ['id' => $invitation->getId()]);
    }

    #[Route('/palanquees/{palanqueeId}/supprimer', name: 'admin_fiche_securite_supprimer', requirements: ['palanqueeId' => '\\d+'], methods: ['POST'])]
    public function supprimer(InvitationFosse $invitation, int $palanqueeId, Request $request, FicheSecuriteRepository $fiches, EntityManagerInterface $em): Response
    {
        $fiche = $this->fiche($invitation, $request, $fiches);
        $palanquee = $fiche->trouverPalanquee($palanqueeId) ?? throw $this->createNotFoundException();
        $fiche->supprimerPalanquee($palanquee);
        $em->flush();

        return $this->redirectToRoute('admin_fiche_securite_show', ['id' => $invitation->getId()]);
    }

    #[Route('/synchroniser', name: 'admin_fiche_securite_synchroniser', methods: ['POST'])]
    public function synchroniser(InvitationFosse $invitation, Request $request, FicheSecuriteRepository $fiches, FicheSecuriteBuilder $builder, EntityManagerInterface $em): Response
    {
        $builder->synchroniser($this->fiche($invitation, $request, $fiches));
        $em->flush();
        $this->addFlash('success', 'Fiche mise à jour : les personnes qui ne sont plus inscrites ont été retirées, les nouveaux inscrits apparaissent dans les restants.');

        return $this->redirectToRoute('admin_fiche_securite_show', ['id' => $invitation->getId()]);
    }

    /** Appelé à chaque glisser-déposer : place un inscrit dans une palanquée, ou le renvoie aux restants (palanqueeId nul). */
    #[Route('/deplacer', name: 'admin_fiche_securite_deplacer', methods: ['POST'])]
    public function deplacer(InvitationFosse $invitation, Request $request, FicheSecuriteRepository $fiches, FicheSecuriteBuilder $builder, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(FicheSecuriteVoter::OUVRIR, $invitation);
        $charge = $request->getPayload();
        if (!$this->isCsrfTokenValid($this->jeton($invitation), (string) $charge->get('_token'))) {
            return new JsonResponse(['ok' => false, 'message' => 'Session expirée, rechargez la page.'], Response::HTTP_FORBIDDEN);
        }

        $fiche = $fiches->findOneByInvitation($invitation) ?? throw $this->createNotFoundException();
        $user = null;
        foreach ($builder->inscrits($invitation) as $inscrit) {
            if ($inscrit->getId() === $charge->getInt('userId')) {
                $user = $inscrit;
            }
        }
        if (!$user instanceof User) {
            return new JsonResponse(['ok' => false, 'message' => 'Cette personne n\'est pas inscrite en liste principale.'], Response::HTTP_BAD_REQUEST);
        }

        $cible = null;
        if (null !== $charge->get('palanqueeId') && '' !== $charge->get('palanqueeId')) {
            $cible = $fiche->trouverPalanquee($charge->getInt('palanqueeId'));
            if (null === $cible) {
                return new JsonResponse(['ok' => false, 'message' => 'Palanquée introuvable.'], Response::HTTP_BAD_REQUEST);
            }
        }

        $fiche->retirer($user);
        $cible?->ajouter($user);
        $em->flush();

        return new JsonResponse(['ok' => true]);
    }

    private function fiche(InvitationFosse $invitation, Request $request, FicheSecuriteRepository $fiches): FicheSecurite
    {
        $this->denyAccessUnlessGranted(FicheSecuriteVoter::OUVRIR, $invitation);
        if (!$this->isCsrfTokenValid($this->jeton($invitation), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        return $fiches->findOneByInvitation($invitation) ?? throw $this->createNotFoundException();
    }

    private function jeton(InvitationFosse $invitation): string
    {
        return 'fiche_securite_'.$invitation->getId();
    }
}
