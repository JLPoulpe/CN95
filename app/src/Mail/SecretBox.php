<?php

namespace App\Mail;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Chiffrement symétrique des secrets stockés en base (clé dérivée de APP_SECRET). */
final class SecretBox
{
    private readonly string $cle;

    public function __construct(#[Autowire('%kernel.secret%')] string $secret)
    {
        $this->cle = hash('sha256', $secret, true);
    }

    public function chiffrer(string $clair): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce.sodium_crypto_secretbox($clair, $nonce, $this->cle));
    }

    public function dechiffrer(string $chiffre): string
    {
        $brut = base64_decode($chiffre, true);
        if (false === $brut || strlen($brut) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new MailException('Configuration mail illisible.');
        }

        $clair = sodium_crypto_secretbox_open(
            substr($brut, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($brut, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $this->cle,
        );
        if (false === $clair) {
            throw new MailException('Impossible de déchiffrer la configuration mail (APP_SECRET modifié ?).');
        }

        return $clair;
    }
}
