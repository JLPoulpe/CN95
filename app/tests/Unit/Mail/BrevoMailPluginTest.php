<?php

namespace App\Tests\Unit\Mail;

use App\Mail\MailException;
use App\Mail\MailMessage;
use App\Mail\Plugin\BrevoMailPlugin;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class BrevoMailPluginTest extends TestCase
{
    private const CONFIG = ['api_key' => 'cle', 'expediteur_nom' => 'CN95', 'expediteur_email' => 'club@example.com'];

    public function testEnvoiPostLeMessageVersBrevo(): void
    {
        $requete = null;
        $http = new MockHttpClient(function (string $method, string $url, array $options) use (&$requete) {
            $requete = ['method' => $method, 'url' => $url, 'body' => json_decode($options['body'], true)];

            return new MockResponse('{"messageId":"1"}', ['http_code' => 201]);
        });

        (new BrevoMailPlugin($http))->send(new MailMessage('membre@example.com', 'Jean Dupont', 'Sujet', '<p>Bonjour</p>'), self::CONFIG);

        $this->assertSame('POST', $requete['method']);
        $this->assertSame('https://api.brevo.com/v3/smtp/email', $requete['url']);
        $this->assertSame(['email' => 'club@example.com', 'name' => 'CN95'], $requete['body']['sender']);
        $this->assertSame([['email' => 'membre@example.com', 'name' => 'Jean Dupont']], $requete['body']['to']);
        $this->assertSame('Sujet', $requete['body']['subject']);
        $this->assertSame('<p>Bonjour</p>', $requete['body']['htmlContent']);
    }

    public function testErreurBrevoLeveUneExceptionMail(): void
    {
        $http = new MockHttpClient(new MockResponse('{"code":"unauthorized"}', ['http_code' => 401]));

        $this->expectException(MailException::class);
        (new BrevoMailPlugin($http))->send(new MailMessage('membre@example.com', null, 'Sujet', '<p>x</p>'), self::CONFIG);
    }

    public function testTestConnexionAccepteLaCleValide(): void
    {
        $http = new MockHttpClient(new MockResponse('{"email":"club@example.com"}', ['http_code' => 200]));

        (new BrevoMailPlugin($http))->testConnection(self::CONFIG);

        $this->addToAssertionCount(1);
    }
}
