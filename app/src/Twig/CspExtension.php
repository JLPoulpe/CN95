<?php

namespace App\Twig;

use App\Security\ContentSecurityPolicySubscriber;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/** csp_nonce() : nonce de la requête courante, à placer sur les rares scripts en ligne. */
final class CspExtension extends AbstractExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('csp_nonce', $this->nonce(...))];
    }

    public function nonce(): string
    {
        $nonce = $this->requestStack->getMainRequest()?->attributes->get(ContentSecurityPolicySubscriber::ATTRIBUT);

        return \is_string($nonce) ? $nonce : '';
    }
}
