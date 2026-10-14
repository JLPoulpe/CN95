<?php

namespace App\Controller;

use App\Enum\TypeDemandeRgpd;
use App\Repository\DemandeRgpdRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Pages RGPD publiques : mentions légales, politique de confidentialité, accusé de lecture et choix sur les communications. */
class RgpdController extends AbstractController
{
    #[Route('/mentions-legales', name: 'app_mentions_legales', methods: ['GET'])]
    public function mentionsLegales(): Response
    {
        return $this->render('rgpd/mentions_legales.html.twig');
    }

    #[Route('/confidentialite', name: 'app_confidentialite', methods: ['GET'])]
    public function confidentialite(): Response
    {
        return $this->render('rgpd/confidentialite.html.twig');
    }

    /** Lien du mail d'information. Le jeton identifie la demande ; accès public, comme les réponses aux invitations. */
    #[Route('/rgpd/accuse/{jeton}', name: 'rgpd_accuse', requirements: ['jeton' => '[0-9a-f]{64}'], methods: ['GET'])]
    public function accuse(string $jeton, DemandeRgpdRepository $demandes, EntityManagerInterface $em): Response
    {
        $demande = $demandes->findOneByJeton($jeton) ?? throw $this->createNotFoundException();
        if (TypeDemandeRgpd::Information !== $demande->getType()) {
            throw $this->createNotFoundException();
        }

        if (!$demande->estRepondue()) {
            $demande->repondre(null);
            $em->flush();
        }

        return $this->render('fosse/reponse.html.twig', [
            'titre' => 'Lecture confirmée',
            'message' => 'Merci, nous avons bien enregistré que vous avez pris connaissance de l\'information sur vos données personnelles.',
        ]);
    }

    /**
     * Choix accepter / refuser. Le GET n'enregistre rien : un antivirus ou un aperçu de mail qui suit le lien
     * ne doit pas donner un accord à la place du membre. L'enregistrement se fait par POST, avec jeton CSRF.
     */
    #[Route('/rgpd/communications/{jeton}', name: 'rgpd_communications', requirements: ['jeton' => '[0-9a-f]{64}'], methods: ['GET', 'POST'])]
    public function communications(string $jeton, Request $request, DemandeRgpdRepository $demandes, EntityManagerInterface $em): Response
    {
        $demande = $demandes->findOneByJeton($jeton) ?? throw $this->createNotFoundException();
        if (TypeDemandeRgpd::Communication !== $demande->getType()) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('rgpd_communications', (string) $request->request->get('_token'))) {
                // HttpException plutôt que AccessDeniedException : pour un visiteur anonyme, cette dernière déclenche une redirection vers la connexion
                throw new HttpException(Response::HTTP_FORBIDDEN, 'Jeton de formulaire invalide.');
            }

            $choix = $request->request->get('choix');
            if (!in_array($choix, ['accepter', 'refuser'], true)) {
                throw $this->createNotFoundException();
            }

            $demande->repondre('accepter' === $choix);
            $em->flush();

            $this->addFlash('success', 'Votre choix a bien été enregistré.');

            return $this->redirectToRoute('rgpd_communications', ['jeton' => $jeton]);
        }

        // Le lien du mail indique le choix pré-sélectionné ; seul le POST l'enregistre
        $preselection = $request->query->get('choix');

        return $this->render('rgpd/communications.html.twig', [
            'demande' => $demande,
            'jeton' => $jeton,
            'preselection' => in_array($preselection, ['accepter', 'refuser'], true) ? $preselection : null,
        ]);
    }
}
