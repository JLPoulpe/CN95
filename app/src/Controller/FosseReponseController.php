<?php

namespace App\Controller;

use App\Entity\ReponseFosse;
use App\Enum\StatutInvitationFosse;
use App\Enum\StatutReponseFosse;
use App\Mail\FosseMailer;
use App\Repository\ReponseFosseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Pages atteintes depuis les liens du mail d'invitation. Accès public : le token identifie la réponse. */
class FosseReponseController extends AbstractController
{
    #[Route('/fosse/r/{token}/{choix}', name: 'fosse_reponse', requirements: ['choix' => 'inscription|refus|desistement'], methods: ['GET'])]
    public function repondre(string $token, string $choix, ReponseFosseRepository $reponses, EntityManagerInterface $em, FosseMailer $mailer): Response
    {
        $reponse = $reponses->findOneByToken($token) ?? throw $this->createNotFoundException();
        $invitation = $reponse->getInvitation();
        $date = $invitation->getDate()->format('d/m/Y');
        $libelle = $invitation->getActivite()->getLibelle();

        if ($invitation->isLienExpire()) {
            return $this->afficher('Lien expiré', sprintf('L\'activité « %s » du %s a déjà eu lieu : ce lien n\'est plus valable.', $libelle, $date));
        }

        if (StatutInvitationFosse::Annulee === $invitation->getStatut()) {
            return $this->afficher('Activité annulée', sprintf('L\'activité « %s » du %s est annulée.', $libelle, $date));
        }

        if ('inscription' === $choix) {
            $this->changerStatut($reponse, StatutReponseFosse::Inscrit, $em);

            return $this->afficher('Inscription enregistrée', sprintf('Vous êtes inscrit(e) à l\'activité « %s » du %s.', $libelle, $date));
        }

        if ('refus' === $choix) {
            $this->changerStatut($reponse, StatutReponseFosse::Refus, $em);

            return $this->afficher('Réponse enregistrée', sprintf('Nous avons bien reçu votre volonté de ne pas participer à l\'activité « %s » du %s.', $libelle, $date));
        }

        if (StatutReponseFosse::Inscrit === $reponse->getStatut()) {
            $this->changerStatut($reponse, StatutReponseFosse::Desistement, $em);
            $mailer->desistementDp($reponse);

            return $this->afficher('Désistement enregistré', sprintf('Nous avons bien pris en compte votre désistement pour l\'activité « %s » du %s. Le responsable en a été informé.', $libelle, $date));
        }

        return $this->afficher('Aucune inscription', sprintf('Vous n\'étiez pas inscrit(e) à l\'activité « %s » du %s.', $libelle, $date));
    }

    private function changerStatut(ReponseFosse $reponse, StatutReponseFosse $statut, EntityManagerInterface $em): void
    {
        if ($reponse->getStatut() !== $statut) {
            $reponse->setStatut($statut);
            $em->flush();
        }
    }

    private function afficher(string $titre, string $message): Response
    {
        return $this->render('fosse/reponse.html.twig', ['titre' => $titre, 'message' => $message]);
    }
}
