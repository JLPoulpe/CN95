<?php

namespace App\Mail;

use App\Message\SendMailMessage;
use Symfony\Component\Messenger\MessageBusInterface;

/** Met le mail en file d'attente ; l'envoi réel est fait par le plugin configuré (voir SendMailMessageHandler). */
class MailSender
{
    private bool $envoiEnAttente = false;

    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function envoyer(MailMessage $message): void
    {
        $this->bus->dispatch(new SendMailMessage($message));
        $this->envoiEnAttente = true;
    }

    /** Vrai si cette requête a ajouté au moins un mail à la file. */
    public function aEnvoisEnAttente(): bool
    {
        return $this->envoiEnAttente;
    }
}
