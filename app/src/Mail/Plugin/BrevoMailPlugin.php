<?php

namespace App\Mail\Plugin;

use App\Mail\MailException;
use App\Mail\MailMessage;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Envoi via l'API transactionnelle Brevo : https://developers.brevo.com/reference/sendtransacemail */
final class BrevoMailPlugin implements MailPluginInterface
{
    private const API = 'https://api.brevo.com/v3';

    public function __construct(private readonly HttpClientInterface $http)
    {
    }

    public function code(): string
    {
        return 'brevo';
    }

    public function label(): string
    {
        return 'Brevo';
    }

    public function configFields(): array
    {
        return [
            'api_key' => ['label' => 'Clé API', 'secret' => true],
        ];
    }

    public function send(MailMessage $message, array $config): void
    {
        $destinataire = ['email' => $message->toEmail];
        if (null !== $message->toNom) {
            $destinataire['name'] = $message->toNom;
        }

        $response = $this->http->request('POST', self::API.'/smtp/email', [
            'headers' => ['api-key' => $config['api_key'] ?? ''],
            'json' => [
                'sender' => ['email' => $config['expediteur_email'] ?? '', 'name' => $config['expediteur_nom'] ?? ''],
                'to' => [$destinataire],
                'subject' => $message->sujet,
                'htmlContent' => $message->html,
            ],
        ]);

        if (201 !== $response->getStatusCode()) {
            throw new MailException(sprintf('Brevo a refusé l\'envoi (HTTP %d) : %s', $response->getStatusCode(), $response->getContent(false)));
        }
    }

    public function testConnection(array $config): void
    {
        $response = $this->http->request('GET', self::API.'/account', [
            'headers' => ['api-key' => $config['api_key'] ?? ''],
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new MailException(sprintf('Connexion Brevo impossible (HTTP %d).', $response->getStatusCode()));
        }
    }
}
