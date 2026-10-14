<?php

namespace App\Mail;

use App\Entity\MailTemplate;
use App\Entity\ReponseFosse;
use App\Entity\User;
use App\Enum\TypeMailTemplate;
use App\Repository\MailTemplateRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Construit les mails de la fosse. Les liens sont générés ici, dans le contexte de la requête web,
 * car le worker Messenger n'a pas l'hôte de l'application.
 */
class FosseMailer
{
    public function __construct(
        private readonly MailSender $sender,
        private readonly MailTemplateRenderer $renderer,
        private readonly MailTemplateRepository $templates,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    /** @return bool Faux si l'envoi a échoué (voir MailSender::envoyer) */
    public function invitation(ReponseFosse $reponse, bool $relance = false): bool
    {
        $invitation = $reponse->getInvitation();
        $template = $invitation->getMailTemplate();
        if (null === $template) {
            return false;
        }

        $date = $invitation->getDate()->format('d/m/Y');
        $libelle = $invitation->getActivite()->getLibelle();

        return $this->envoyer(
            $reponse->getUser(),
            ($relance ? 'Rappel : ' : 'Invitation : ').$libelle.' du '.$date,
            $template,
            ['date' => $date, 'activite' => $libelle],
            $invitation->getCommentaire(),
            [
                'intro' => 'Merci d\'indiquer votre choix :',
                'items' => [
                    'Je m\'inscris à cette activité' => $this->lien($reponse, 'inscription'),
                    'Je ne participe pas à cette activité' => $this->lien($reponse, 'refus'),
                    'Je suis inscrit(e) mais je ne peux plus participer' => $this->lien($reponse, 'desistement'),
                ],
            ],
        );
    }

    /** @return bool Faux si l'envoi a échoué (voir MailSender::envoyer) */
    public function annulation(ReponseFosse $reponse): bool
    {
        $date = $reponse->getInvitation()->getDate()->format('d/m/Y');
        $libelle = $reponse->getInvitation()->getActivite()->getLibelle();

        return $this->envoyerParDefaut(
            TypeMailTemplate::Annulation,
            $reponse->getUser(),
            'Annulation : '.$libelle.' du '.$date,
            ['date' => $date, 'activite' => $libelle],
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

        return $this->envoyerParDefaut(
            TypeMailTemplate::DesistementResponsable,
            $dp,
            'Désistement : '.$libelle.' du '.$date,
            ['date' => $date, 'activite' => $libelle, 'membre' => $reponse->getUser()->getNomComplet()],
        );
    }

    /** @param array<string, string> $variables */
    private function envoyerParDefaut(TypeMailTemplate $type, User $destinataire, string $sujet, array $variables): bool
    {
        $template = $this->templates->parDefaut($type);
        if (null === $template) {
            return false;
        }

        return $this->envoyer($destinataire, $sujet, $template, $variables);
    }

    /**
     * @param array<string, string>                                   $variables
     * @param array{intro: string, items: array<string, string>}|null $liens
     */
    private function envoyer(User $destinataire, string $sujet, MailTemplate $template, array $variables, ?string $commentaire = null, ?array $liens = null): bool
    {
        return $this->sender->envoyer(new MailMessage(
            $destinataire->getEmail(),
            $destinataire->getPrenom().' '.$destinataire->getNom(),
            $sujet,
            $this->renderer->rendre($template, $variables, $commentaire, $liens),
        ));
    }

    private function lien(ReponseFosse $reponse, string $choix): string
    {
        return $this->urls->generate('fosse_reponse', ['token' => $reponse->getToken(), 'choix' => $choix], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
