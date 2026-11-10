<?php

namespace App\Controller;

use App\Entity\InvitationFosse;
use App\Entity\ReponseFosse;
use App\Enum\StatutInvitationFosse;
use App\Enum\StatutReponseFosse;
use App\Mail\FosseMailer;
use App\Repository\ReponseFosseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Pages atteintes depuis les liens du mail d'invitation. Accès public : le token identifie la réponse. */
class FosseReponseController extends AbstractController
{
    #[Route('/fosse/r/{token}/{choix}', name: 'fosse_reponse', requirements: ['choix' => 'inscription|refus|desistement'], methods: ['GET', 'POST'])]
    public function repondre(Request $request, string $token, string $choix, ReponseFosseRepository $reponses, EntityManagerInterface $em, FosseMailer $mailer): Response
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

        // En GET (ouverture du lien du mail, éventuellement par un antivirus ou un aperçu) rien n'est enregistré : le membre confirme par un clic (POST)
        if (!$request->isMethod('POST')) {
            return $this->confirmer($token, $choix, $libelle, $date);
        }

        if ('inscription' === $choix) {
            return $this->inscrire($reponse, $reponses, $em, $libelle, $date);
        }

        if ('refus' === $choix) {
            $this->changerStatut($reponse, StatutReponseFosse::Refus, $em);

            return $this->afficher('Réponse enregistrée', sprintf('Nous avons bien reçu votre volonté de ne pas participer à l\'activité « %s » du %s.', $libelle, $date));
        }

        // Pour le membre, la liste d'attente n'existe pas : se désister depuis l'une ou l'autre liste donne le même résultat
        if (in_array($reponse->getStatut(), [StatutReponseFosse::Inscrit, StatutReponseFosse::ListeAttente], true)) {
            $this->desister($reponse, $reponses, $em);
            $mailer->desistementDp($reponse);

            return $this->afficher('Désistement enregistré', sprintf('Nous avons bien pris en compte votre désistement pour l\'activité « %s » du %s. Le responsable en a été informé.', $libelle, $date));
        }

        return $this->afficher('Aucune inscription', sprintf('Vous n\'étiez pas inscrit(e) à l\'activité « %s » du %s.', $libelle, $date));
    }

    /** Les 30 premiers (limite d'inscrits) vont en liste principale, les suivants en liste d'attente ; au-delà, plus de place. */
    private function inscrire(ReponseFosse $reponse, ReponseFosseRepository $reponses, EntityManagerInterface $em, string $libelle, string $date): Response
    {
        $statut = $em->wrapInTransaction(function () use ($reponse, $reponses, $em): ?StatutReponseFosse {
            // Verrou sur l'invitation : deux inscriptions simultanées ne doivent pas dépasser la limite
            $invitation = $em->find(InvitationFosse::class, $reponse->getInvitation()->getId(), LockMode::PESSIMISTIC_WRITE);

            // Déjà dans une des deux listes : on conserve sa place (un second clic ne change rien)
            if (in_array($reponse->getStatut(), [StatutReponseFosse::Inscrit, StatutReponseFosse::ListeAttente], true)) {
                return $reponse->getStatut();
            }

            $statut = $invitation->statutPourInscription(
                $reponses->compter($invitation, StatutReponseFosse::Inscrit),
                $reponses->compter($invitation, StatutReponseFosse::ListeAttente),
            );
            if (null !== $statut) {
                $this->changerStatut($reponse, $statut, $em);
            }

            return $statut;
        });

        if (null === $statut) {
            return $this->afficher('Plus de place disponible', sprintf('Il n\'y a plus de place disponible pour l\'activité « %s » du %s. Si besoin, rapprochez-vous de votre moniteur référent.', $libelle, $date));
        }

        return $this->afficher('Inscription enregistrée', sprintf('Vous avez bien été mis en attente d\'approbation à l\'activité « %s » du %s.', $libelle, $date));
    }

    /** Le désistement d'un inscrit libère une place en liste principale : le premier de la liste d'attente la prend, ce qui rouvre une place en liste d'attente. */
    private function desister(ReponseFosse $reponse, ReponseFosseRepository $reponses, EntityManagerInterface $em): void
    {
        $em->wrapInTransaction(function () use ($reponse, $reponses, $em): void {
            $invitation = $em->find(InvitationFosse::class, $reponse->getInvitation()->getId(), LockMode::PESSIMISTIC_WRITE);

            $etaitInscrit = StatutReponseFosse::Inscrit === $reponse->getStatut();
            $this->changerStatut($reponse, StatutReponseFosse::Desistement, $em);

            // Seul le désistement d'un inscrit libère une place en liste principale (promotion silencieuse : aucun mail)
            if ($etaitInscrit && null !== $suivant = $reponses->premierEnListeAttente($invitation)) {
                $this->changerStatut($suivant, StatutReponseFosse::Inscrit, $em);
            }
        });
    }

    private function changerStatut(ReponseFosse $reponse, StatutReponseFosse $statut, EntityManagerInterface $em): void
    {
        if ($reponse->getStatut() !== $statut) {
            $reponse->setStatut($statut);
            $em->flush();
        }
    }

    private function confirmer(string $token, string $choix, string $libelle, string $date): Response
    {
        [$titre, $bouton, $phrase] = match ($choix) {
            'inscription' => ['Confirmer mon inscription', 'Je m\'inscris', 'Vous êtes sur le point de vous inscrire à l\'activité « %s » du %s.'],
            'refus' => ['Confirmer mon refus', 'Je ne participerai pas', 'Vous êtes sur le point d\'indiquer que vous ne participerez pas à l\'activité « %s » du %s.'],
            default => ['Confirmer mon désistement', 'Je me désiste', 'Vous êtes sur le point de vous désister de l\'activité « %s » du %s.'],
        };

        return $this->render('fosse/confirmation.html.twig', [
            'titre' => $titre,
            'message' => sprintf($phrase, $libelle, $date),
            'bouton' => $bouton,
            'route' => 'fosse_reponse',
            'parametres' => ['token' => $token, 'choix' => $choix],
        ]);
    }

    private function afficher(string $titre, string $message): Response
    {
        return $this->render('fosse/reponse.html.twig', ['titre' => $titre, 'message' => $message]);
    }
}
