<?php

namespace App\Mail;

use App\Entity\ReponseFosse;
use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Construit les mails de la fosse. Les liens sont générés ici, dans le contexte de la requête web,
 * car le worker Messenger n'a pas l'hôte de l'application.
 */
class FosseMailer
{
    public function __construct(
        private readonly MailSender $sender,
        private readonly MailRenderer $renderer,
        private readonly ContenuMailSanitizer $sanitizer,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /** @return bool Faux si l'envoi a échoué (voir MailSender::envoyer) */
    public function invitation(ReponseFosse $reponse, bool $relance = false): bool
    {
        $invitation = $reponse->getInvitation();
        $date = $invitation->getDate()->format('d/m/Y');
        $libelle = $invitation->getActivite()->getLibelle();

        return $this->envoyer(
            $reponse->getUser(),
            ($relance ? 'Rappel : ' : 'Invitation : ').$libelle.' du '.$date,
            $this->htmlInvitation($libelle, $date, $invitation->getCommentaire(), $reponse),
        );
    }

    /**
     * Corps d'un mail d'invitation : base + commentaire + liens de réponse.
     * Sans réponse (mail d'essai ou aperçu), les liens sont factices.
     */
    public function htmlInvitation(string $activite, string $date, ?string $commentaire, ?ReponseFosse $reponse = null): string
    {
        return $this->renderer->rendre('mail/invitation.html.twig', [
            'activite' => $activite,
            'date' => $date,
            'commentaire' => $this->sanitizer->commentaire($commentaire),
            'liens' => [
                'Je m\'inscris à cette activité' => $this->lien($reponse, 'inscription'),
                'Je ne participe pas à cette activité' => $this->lien($reponse, 'refus'),
                'Je suis inscrit(e) mais je ne peux plus participer' => $this->lien($reponse, 'desistement'),
            ],
        ]);
    }

    /** @return bool Faux si l'envoi a échoué (voir MailSender::envoyer) */
    public function annulation(ReponseFosse $reponse): bool
    {
        $date = $reponse->getInvitation()->getDate()->format('d/m/Y');
        $libelle = $reponse->getInvitation()->getActivite()->getLibelle();

        return $this->envoyer(
            $reponse->getUser(),
            'Annulation : '.$libelle.' du '.$date,
            $this->renderer->rendre('mail/annulation.html.twig', ['activite' => $libelle, 'date' => $date]),
        );
    }

    /**
     * Prévient le responsable qu'un membre inscrit ne peut plus participer.
     *
     * @return bool Faux s'il n'y a pas de responsable ou si l'envoi a échoué
     */
    public function desistementDp(ReponseFosse $reponse): bool
    {
        $invitation = $reponse->getInvitation();
        $dp = $invitation->getDp();
        if (null === $dp) {
            return false;
        }

        $libelle = $invitation->getActivite()->getLibelle();
        $date = $invitation->getDate()->format('d/m/Y');

        return $this->envoyer(
            $dp,
            'Désistement : '.$libelle.' du '.$date,
            $this->renderer->rendre('mail/desistement.html.twig', [
                'membre' => $reponse->getUser()->getNomComplet(),
                'activite' => $libelle,
                'date' => $date,
            ]),
        );
    }

    private function envoyer(User $destinataire, string $sujet, string $html): bool
    {
        return $this->sender->envoyer(new MailMessage(
            $destinataire->getEmail(),
            $destinataire->getPrenom().' '.$destinataire->getNom(),
            $sujet,
            $html,
        ));
    }

    private function lien(?ReponseFosse $reponse, string $choix): string
    {
        if (null === $reponse) {
            return '#';
        }

        return $this->urls->generate('fosse_reponse', ['token' => $reponse->getToken(), 'choix' => $choix], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
