<?php

namespace App\Mail;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * N'autorise dans l'attribut style que ce que produit l'éditeur : couleur du texte, couleur de fond, alignement.
 * Tout le reste (position, taille, url(), etc.) est supprimé : un contenu de mail ne doit pas pouvoir recouvrir l'écran.
 */
final class StyleAttributeSanitizer implements AttributeSanitizerInterface
{
    private const COULEUR = '/^(?:#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}(?:\.\d+)?%?\s*(?:,\s*\d{1,3}(?:\.\d+)?%?\s*){2,3}\)|[a-z]{3,20})$/i';
    private const ALIGNEMENT = '/^(?:left|right|center|justify)$/i';

    private const PROPRIETES = [
        'color' => self::COULEUR,
        'background-color' => self::COULEUR,
        'text-align' => self::ALIGNEMENT,
    ];

    public function getSupportedElements(): ?array
    {
        return null;
    }

    public function getSupportedAttributes(): ?array
    {
        return ['style'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $retenues = [];
        foreach (explode(';', $value) as $declaration) {
            $morceaux = explode(':', $declaration, 2);
            if (2 !== \count($morceaux)) {
                continue;
            }
            $propriete = strtolower(trim($morceaux[0]));
            $valeur = trim($morceaux[1]);
            if (isset(self::PROPRIETES[$propriete]) && 1 === preg_match(self::PROPRIETES[$propriete], $valeur)) {
                $retenues[] = $propriete.': '.$valeur.';';
            }
        }

        return [] === $retenues ? null : implode(' ', $retenues);
    }
}
