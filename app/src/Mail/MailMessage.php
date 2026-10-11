<?php

namespace App\Mail;

/** Message indépendant du plugin d'envoi (sérialisable pour Messenger). */
final readonly class MailMessage
{
    public function __construct(
        public string $toEmail,
        public ?string $toNom,
        public string $sujet,
        public string $html,
    ) {
    }
}
