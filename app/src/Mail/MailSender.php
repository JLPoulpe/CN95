<?php

namespace App\Mail;

use App\Message\SendMailMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/** Met le mail en file d'attente ; l'envoi réel est fait par le plugin configuré (voir SendMailMessageHandler). */
class MailSender
{
    private bool $envoiEnAttente = false;

    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Met le mail en file ; ne lève jamais (un transport synchrone ou une config mail absente ne doit pas
     * faire échouer l'action qui a déclenché l'envoi).
     *
     * @return bool Faux si la mise en file a échoué (l'erreur est journalisée)
     */
    public function envoyer(MailMessage $message): bool
    {
        try {
            $this->bus->dispatch(new SendMailMessage($message));
        } catch (\Throwable $e) {
            $this->logger->error('Échec de l\'envoi d\'un mail à {destinataire} : {erreur}', [
                'destinataire' => $message->toEmail,
                'erreur' => $e->getMessage(),
                'exception' => $e,
            ]);

            return false;
        }

        $this->envoiEnAttente = true;

        return true;
    }

    /** Vrai si cette requête a ajouté au moins un mail à la file. */
    public function aEnvoisEnAttente(): bool
    {
        return $this->envoiEnAttente;
    }
}
