<?php

namespace App\Mail;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Nettoie le HTML saisi dans l'éditeur avant stockage : balises de mise en forme, liens web/mail et styles inline
 * (couleurs, alignement de l'éditeur). Scripts, images et autres balises sont retirés.
 */
final class ContenuMailSanitizer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowSafeElements()
            ->allowElement('h1')->allowElement('h2')->allowElement('h3')
            ->allowAttribute('style', '*')
            ->allowAttribute('class', ['p', 'h1', 'h2', 'h3', 'li', 'ol', 'ul'])
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowRelativeLinks(false)
            ->allowLinkHosts(null)
            ->forceHttpsUrls(false);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function nettoyer(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }

    /**
     * HTML sûr d'un commentaire d'invitation. Un texte brut (commentaires saisis avant l'éditeur riche) devient
     * un paragraphe échappé avec retours à la ligne ; un HTML de l'éditeur est nettoyé. Idempotent : une valeur
     * déjà passée ici peut être repassée sans changer.
     */
    public function commentaire(?string $texte): string
    {
        if (null === $texte || '' === trim($texte)) {
            return '';
        }

        if ($texte === strip_tags($texte)) {
            return '<p>'.nl2br(htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>';
        }

        return $this->nettoyer($texte);
    }
}
