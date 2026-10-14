<?php

namespace App\Mail;

use App\Entity\User;
use App\Enum\TypeMailTemplate;
use App\Repository\MailTemplateRepository;

/** Mails liés au compte utilisateur. Le lien est généré dans le contrôleur (contexte web), comme pour FosseMailer. */
class CompteMailer
{
    public function __construct(
        private readonly MailSender $sender,
        private readonly MailTemplateRenderer $renderer,
        private readonly MailTemplateRepository $templates,
    ) {
    }

    /** @return bool Faux si l'envoi a échoué (voir MailSender::envoyer) ou s'il n'existe pas de modèle par défaut */
    public function activation(User $user, string $lien): bool
    {
        $template = $this->templates->parDefaut(TypeMailTemplate::ActivationCompte);
        if (null === $template) {
            return false;
        }

        return $this->sender->envoyer(new MailMessage(
            $user->getEmail(),
            $user->getPrenom().' '.$user->getNom(),
            'Activation de votre compte CN95 Plongée',
            $this->renderer->rendre($template, ['prenom' => (string) $user->getPrenom(), 'nom' => (string) $user->getNom()], null, [
                'intro' => 'Un compte a été créé pour vous sur l\'espace CN95 Plongée. Pour choisir votre mot de passe et activer votre compte, cliquez sur le lien suivant :',
                'items' => ['Activer mon compte' => $lien],
            ]),
        ));
    }
}
