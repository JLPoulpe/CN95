<?php

namespace App\Controller;

use App\Enum\TypeDemandeRgpd;
use App\Repository\DemandeRgpdRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Pages RGPD publiques : mentions légales, politique de confidentialité, accusé de lecture. */
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
     * Ancien lien « accepter / refuser les communications » des mails d'information déjà envoyés. Les communications
     * du bureau ne dépendent plus d'un choix : la page l'explique et indique comment s'y opposer. Aucune réponse n'est enregistrée.
     */
    #[Route('/rgpd/communications/{jeton}', name: 'rgpd_communications', requirements: ['jeton' => '[0-9a-f]{64}'], methods: ['GET'])]
    public function communications(string $jeton, DemandeRgpdRepository $demandes): Response
    {
        $demande = $demandes->findOneByJeton($jeton) ?? throw $this->createNotFoundException();
        if (TypeDemandeRgpd::Communication !== $demande->getType()) {
            throw $this->createNotFoundException();
        }

        return $this->render('rgpd/communications.html.twig');
    }
}
