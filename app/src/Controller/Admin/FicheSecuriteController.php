<?php

namespace App\Controller\Admin;

use App\Entity\FicheSecurite;
use App\Entity\InvitationFosse;
use App\Entity\Palanquee;
use App\Entity\User;
use App\Fosse\FicheSecuriteBuilder;
use App\Fosse\FicheSecuritePdf;
use App\Repository\FicheSecuriteRepository;
use App\Security\Voter\FicheSecuriteVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
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
            'groupes_ajout' => $builder->restantsParAptitude($fiche),
            'nb_inscrits' => count($builder->inscrits($invitation)),
        ]);
    }

    /** Fiche de sécurité club (formulaire UCPA) pré-remplie, au format PDF. */
    #[Route('/pdf', name: 'admin_fiche_securite_pdf', methods: ['GET'])]
    public function pdf(InvitationFosse $invitation, FicheSecuriteRepository $fiches, FicheSecuritePdf $pdf): Response
    {
        $this->denyAccessUnlessGranted(FicheSecuriteVoter::OUVRIR, $invitation);
        $fiche = $fiches->findOneByInvitation($invitation) ?? throw $this->createNotFoundException();

        $reponse = new Response($pdf->generer($fiche), Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
        $reponse->headers->set('Content-Disposition', $reponse->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            sprintf('fiche-securite-%s.pdf', $invitation->getDate()->format('Y-m-d')),
        ));

        return $reponse;
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

    /** Enregistre les paramètres de plongée d'une palanquée (profondeur max., DTR, durée, type). */
    #[Route('/palanquees/{palanqueeId}/parametres', name: 'admin_fiche_securite_parametres', requirements: ['palanqueeId' => '\\d+'], methods: ['POST'])]
    public function parametres(InvitationFosse $invitation, int $palanqueeId, Request $request, FicheSecuriteRepository $fiches, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted(FicheSecuriteVoter::OUVRIR, $invitation);
        $charge = $request->getPayload();
        if (!$this->isCsrfTokenValid($this->jeton($invitation), (string) $charge->get('_token'))) {
            return new JsonResponse(['ok' => false, 'message' => 'Session expirée, rechargez la page.'], Response::HTTP_FORBIDDEN);
        }
        $fiche = $fiches->findOneByInvitation($invitation) ?? throw $this->createNotFoundException();
        $palanquee = $fiche->trouverPalanquee($palanqueeId) ?? throw $this->createNotFoundException();

        $valeurs = [];
        foreach (['profondeurMax' => 100, 'dtr' => 999, 'duree' => 999] as $champ => $max) {
            $brut = $charge->get($champ);
            if (null === $brut || '' === $brut) {
                $valeurs[$champ] = null;
                continue;
            }
            if (!is_numeric($brut) || (int) $brut != $brut || $brut < 0 || $brut > $max) {
                return new JsonResponse(['ok' => false, 'message' => sprintf('Valeur invalide : « %s » doit être un entier entre 0 et %d.', $champ, $max)], Response::HTTP_BAD_REQUEST);
            }
            $valeurs[$champ] = (int) $brut;
        }
        $type = $charge->get('type');
        $type = (null === $type || '' === $type) ? null : (string) $type;
        if (null !== $type && !isset(Palanquee::TYPES[$type])) {
            return new JsonResponse(['ok' => false, 'message' => 'Type de palanquée inconnu.'], Response::HTTP_BAD_REQUEST);
        }

        $palanquee->setParametres($valeurs['profondeurMax'], $valeurs['dtr'], $valeurs['duree'], $type);
        $em->flush();

        return new JsonResponse(['ok' => true]);
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
        $exterieur = $charge->getInt('exterieur');
        if ($charge->has('exterieur') && ($exterieur < 1 || $exterieur > Palanquee::EXTERIEURS)) {
            return new JsonResponse(['ok' => false, 'message' => 'Moniteur extérieur inconnu.'], Response::HTTP_BAD_REQUEST);
        }
        $user = null;
        foreach ($builder->inscrits($invitation) as $inscrit) {
            if ($inscrit->getId() === $charge->getInt('userId')) {
                $user = $inscrit;
            }
        }
        if ($exterieur < 1 && !$user instanceof User) {
            return new JsonResponse(['ok' => false, 'message' => 'Cette personne n\'est pas inscrite en liste principale.'], Response::HTTP_BAD_REQUEST);
        }

        $cible = null;
        if (null !== $charge->get('palanqueeId') && '' !== $charge->get('palanqueeId')) {
            $cible = $fiche->trouverPalanquee($charge->getInt('palanqueeId'));
            if (null === $cible) {
                return new JsonResponse(['ok' => false, 'message' => 'Palanquée introuvable.'], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($exterieur >= 1) {
            $fiche->retirerExterieur($exterieur);
            $cible?->ajouterExterieur($exterieur);
        } else {
            $fiche->retirer($user);
            $cible?->ajouter($user);
        }
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
