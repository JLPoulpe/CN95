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

    public function activation(User $user, string $lien): void
    {
        $this->sender->envoyer(new MailMessage(
            $user->getEmail(),
            $user->getPrenom().' '.$user->getNom(),
            'Activation de votre compte CN95 Plongée',
            $this->twig->render('mail/compte/activation.html.twig', ['destinataire' => $user, 'lien' => $lien]),
        ));
    }
}
