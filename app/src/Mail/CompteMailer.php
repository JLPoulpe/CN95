<?php

namespace App\Mail;

use App\Entity\User;
use Twig\Environment;

/** Mails liés au compte utilisateur. Le lien est généré dans le contrôleur (contexte web), comme pour FosseMailer. */
class CompteMailer
{
    public function __construct(
        private readonly MailSender $sender,
        private readonly Environment $twig,
    ) {
    }

    /** @return bool Faux si l'envoi a échoué (voir MailSender::envoyer) */
    public function activation(User $user, string $lien): bool
    {
        return $this->sender->envoyer(new MailMessage(
            $user->getEmail(),
            $user->getPrenom().' '.$user->getNom(),
            'Activation de votre compte CN95 Plongée',
            $this->twig->render('mail/compte/activation.html.twig', ['destinataire' => $user, 'lien' => $lien]),
        ));
    }
}
