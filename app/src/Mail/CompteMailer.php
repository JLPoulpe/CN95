<?php

namespace App\Mail;

use App\Entity\User;

/** Mails liés au compte utilisateur. Le lien est généré dans le contrôleur (contexte web), comme pour FosseMailer. */
class CompteMailer
{
    public function __construct(
        private readonly MailSender $sender,
        private readonly MailRenderer $renderer,
    ) {
    }

    /**
     * Mail de création de compte : information RGPD et lien d'activation (choix du mot de passe).
     *
     * @return bool Faux si l'envoi a échoué (voir MailSender::envoyer)
     */
    public function activation(User $user, string $lien): bool
    {
        return $this->sender->envoyer(new MailMessage(
            $user->getEmail(),
            $user->getPrenom().' '.$user->getNom(),
            'Activation de votre compte CN95 Plongée',
            $this->renderer->rendre('mail/compte/activation.html.twig', [
                'prenom' => (string) $user->getPrenom(),
                'nom' => (string) $user->getNom(),
                'lien' => $lien,
            ]),
        ));
    }

    /** Mail « mot de passe oublié » : lien valable 1 heure. */
    public function reinitialisation(User $user, string $lien): bool
    {
        return $this->sender->envoyer(new MailMessage(
            $user->getEmail(),
            $user->getPrenom().' '.$user->getNom(),
            'Réinitialisation de votre mot de passe CN95 Plongée',
            $this->renderer->rendre('mail/compte/reinitialisation.html.twig', [
                'prenom' => (string) $user->getPrenom(),
                'lien' => $lien,
            ]),
        ));
    }
}
