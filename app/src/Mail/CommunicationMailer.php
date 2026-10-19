<?php

namespace App\Mail;

use App\Entity\Communication;
use App\Entity\User;

/** Mail d'une communication officielle : base + contenu saisi dans l'éditeur, sans lien ajouté. */
class CommunicationMailer
{
    public function __construct(
        private readonly MailSender $sender,
        private readonly MailRenderer $renderer,
        private readonly ContenuMailSanitizer $sanitizer,
    ) {
    }

    /** Corps du mail, tel que reçu par les destinataires. */
    public function html(Communication $communication): string
    {
        return $this->renderer->rendre('mail/communication.html.twig', [
            'contenu' => $this->sanitizer->nettoyer($communication->getContenu()),
        ]);
    }

    /**
     * @param bool $test Mail d'essai : l'objet est précédé de « [Test] »
     *
     * @return bool Faux si l'envoi a échoué (voir MailSender::envoyer)
     */
    public function envoyer(Communication $communication, User $destinataire, bool $test = false): bool
    {
        return $this->sender->envoyer(new MailMessage(
            (string) $destinataire->getEmail(),
            $destinataire->getPrenom().' '.$destinataire->getNom(),
            ($test ? '[Test] ' : '').$communication->getObjet(),
            $this->html($communication),
        ));
    }
}
