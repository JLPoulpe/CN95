<?php

namespace App\Mail\Plugin;

use App\Mail\MailMessage;

/**
 * Un plugin d'envoi de mails. Tout service implémentant cette interface est automatiquement
 * enregistré (tag app.mail_plugin) et proposé dans l'administration.
 */
interface MailPluginInterface
{
    public function code(): string;

    public function label(): string;

    /**
     * Champs de configuration propres au plugin, affichés dans l'administration.
     *
     * @return array<string, array{label: string, secret?: bool}>
     */
    public function configFields(): array;

    /**
     * @param array<string, string> $config Valeurs des champs du plugin, plus expediteur_nom et expediteur_email
     *
     * @throws \App\Mail\MailException
     */
    public function send(MailMessage $message, array $config): void;

    /**
     * @param array<string, string> $config
     *
     * @throws \App\Mail\MailException
     */
    public function testConnection(array $config): void;
}
