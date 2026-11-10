<?php

namespace App\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En-têtes de sécurité, dont la Content-Security-Policy avec nonce par requête : seuls les scripts de l'application (même origine) et les scripts
 * en ligne portant le nonce (importmap d'AssetMapper) s'exécutent. Un script injecté par une faille XSS est bloqué.
 * Les styles en ligne restent permis (attributs style des gabarits).
 */
final class ContentSecurityPolicySubscriber implements EventSubscriberInterface
{
    public const ATTRIBUT = '_csp_nonce';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['surRequete', 2048],
            KernelEvents::RESPONSE => ['surReponse', -16],
        ];
    }

    public function surRequete(RequestEvent $event): void
    {
        if ($event->isMainRequest()) {
            $event->getRequest()->attributes->set(self::ATTRIBUT, base64_encode(random_bytes(18)));
        }
    }

    public function surReponse(ResponseEvent $event): void
    {
        $reponse = $event->getResponse();
        $nonce = $event->getRequest()->attributes->get(self::ATTRIBUT);
        if (!$event->isMainRequest() || !\is_string($nonce) || $reponse->headers->has('Content-Security-Policy')) {
            return;
        }

        $reponse->headers->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-".$nonce."'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]));
        $reponse->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');

        // Autres en-têtes de sécurité, envoyés par l'application pour ne pas dépendre de la configuration Apache de l'hébergeur
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');
        $reponse->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $reponse->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if ($event->getRequest()->isSecure()) {
            $reponse->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
    }
}
