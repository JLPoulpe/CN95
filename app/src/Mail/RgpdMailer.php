<?php

namespace App\Mail;

use App\Entity\User;
use Twig\Environment;

/**
 * Mails RGPD : information des membres (accusé de lecture et choix sur les communications) et rappels de purge au bureau.
 * Les liens sont générés par l'appelant (commandes de la console) avec les jetons des demandes.
 */
class RgpdMailer
{
    /** Version du texte d'information. À incrémenter avec le texte, pour garder la preuve de ce qui a été affiché. */
    public const VERSION_TEXTE = '2026-10';

    public function __construct(
        private readonly MailSender $sender,
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param array{accuse: string, accepter: string, refuser: string, politique: string} $liens
     *
     * @return bool Faux si l'envoi a échoué (voir MailSender::envoyer)
     */
    public function information(User $user, array $liens, bool $representantLegal = false): bool
    {
        $gabarit = $representantLegal ? 'mail/rgpd/information_representant.html.twig' : 'mail/rgpd/information.html.twig';

        return $this->envoyer($user, 'Vos données personnelles – CN95 Plongée', $gabarit, [
            'liens' => $liens,
            'version' => self::VERSION_TEXTE,
        ]);
    }

    /**
     * Rappel au bureau : décrit ce qui sera supprimé à la purge du 31/08.
     *
     * @param array{invitations: int, reponses: int, utilisateurs: int, demandes: int} $compte Résultat de la simulation de purge
     *
     * @return bool Faux si l'envoi a échoué (voir MailSender::envoyer)
     */
    public function rappelPurge(User $destinataire, \DateTimeImmutable $limite, int $joursRestants, array $compte): bool
    {
        return $this->envoyer(
            $destinataire,
            sprintf('Rappel : suppression des données dans %s jour%s – CN95 Plongée', $joursRestants, $joursRestants > 1 ? 's' : ''),
            'mail/rgpd/rappel_purge.html.twig',
            [
                'limite' => $limite->format('d/m/Y'),
                'jours' => $joursRestants,
                'compte' => $compte,
            ],
        );
    }

    /** @param array<string, mixed> $contexte */
    private function envoyer(User $user, string $sujet, string $gabarit, array $contexte): bool
    {
        $contenu = $this->twig->render($gabarit, $contexte + [
            'prenom' => (string) $user->getPrenom(),
            'nom' => (string) $user->getNom(),
        ]);

        return $this->sender->envoyer(new MailMessage(
            $user->getEmail(),
            $user->getPrenom().' '.$user->getNom(),
            $sujet,
            $this->twig->render('mail/_base.html.twig', ['contenu' => $contenu]),
        ));
    }
}
