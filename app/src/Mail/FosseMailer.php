<?php

namespace App\Mail;

use App\Entity\ReponseFosse;
use App\Entity\User;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Construit les mails de la fosse. Les liens sont générés ici, dans le contexte de la requête web,
 * car le worker Messenger n'a pas l'hôte de l'application.
 */
class FosseMailer
{
    public function __construct(
        private readonly MailSender $sender,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public function invitation(ReponseFosse $reponse, bool $relance = false): void
    {
        $date = $reponse->getInvitation()->getDate()->format('d/m/Y');
        $libelle = $reponse->getInvitation()->getActivite()->getLibelle();

        $this->envoyer(
            $reponse->getUser(),
            ($relance ? 'Rappel : ' : 'Invitation : ').$libelle.' du '.$date,
            'mail/fosse/invitation.html.twig',
            [
                'libelle' => $libelle,
                'date' => $date,
                'commentaire' => $reponse->getInvitation()->getCommentaire(),
                'liens' => [
                    'inscription' => $this->lien($reponse, 'inscription'),
                    'refus' => $this->lien($reponse, 'refus'),
                    'desistement' => $this->lien($reponse, 'desistement'),
                ],
            ],
        );
    }

    public function annulation(ReponseFosse $reponse): void
    {
        $date = $reponse->getInvitation()->getDate()->format('d/m/Y');
        $libelle = $reponse->getInvitation()->getActivite()->getLibelle();

        $this->envoyer($reponse->getUser(), 'Annulation : '.$libelle.' du '.$date, 'mail/fosse/annulation.html.twig', ['libelle' => $libelle, 'date' => $date]);
    }

    /** Prévient le responsable qu'un membre inscrit ne peut plus participer. */
    public function desistementDp(ReponseFosse $reponse): void
    {
        $invitation = $reponse->getInvitation();
        $dp = $invitation->getDp();
        if (null === $dp) {
            return;
        }

        $libelle = $invitation->getActivite()->getLibelle();

        $this->envoyer($dp, 'Désistement : '.$libelle.' du '.$invitation->getDate()->format('d/m/Y'), 'mail/fosse/desistement_dp.html.twig', [
            'libelle' => $libelle,
            'membre' => $reponse->getUser(),
            'date' => $invitation->getDate()->format('d/m/Y'),
        ]);
    }

    /** @param array<string, mixed> $variables */
    private function envoyer(User $destinataire, string $sujet, string $template, array $variables): void
    {
        $this->sender->envoyer(new MailMessage(
            $destinataire->getEmail(),
            $destinataire->getPrenom().' '.$destinataire->getNom(),
            $sujet,
            $this->twig->render($template, ['destinataire' => $destinataire] + $variables),
        ));
    }

    private function lien(ReponseFosse $reponse, string $choix): string
    {
        return $this->urls->generate('fosse_reponse', ['token' => $reponse->getToken(), 'choix' => $choix], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
