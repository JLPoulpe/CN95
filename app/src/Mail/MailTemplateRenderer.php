<?php

namespace App\Mail;

use App\Entity\MailTemplate;
use Twig\Environment;

/**
 * Assemble un mail : commentaire, bloc de liens obligatoires, contenu du template, puis la mise en page commune.
 * Les variables {{nom}} du template sont remplacées par leur valeur échappée ; les liens sont ajoutés automatiquement.
 * Par défaut le bloc de liens précède le contenu ; si le template contient {{liens}}, le bloc est placé à cet endroit.
 */
class MailTemplateRenderer
{
    private const TOKEN_LIENS = '/\{\{\s*liens\s*\}\}/';

    public function __construct(
        private readonly Environment $twig,
        private readonly ContenuMailSanitizer $sanitizer,
    ) {
    }

    /**
     * @param array<string, string>                                   $variables Valeurs des {{variables}} du template (date, activite, prenom, nom, membre)
     * @param array{intro: string, items: array<string, string>}|null $liens     Libellé => URL, précédés d'une phrase d'introduction
     */
    public function rendre(MailTemplate $template, array $variables = [], ?string $commentaire = null, ?array $liens = null): string
    {
        $corps = $this->sanitizer->commentaire($commentaire);

        $bloc = $this->blocLiens($liens);
        $contenu = $this->substituer($template->getContenu(), $variables);

        // {{liens}} indique où placer le bloc ; à défaut, il précède le contenu
        if (preg_match(self::TOKEN_LIENS, $contenu)) {
            $corps .= preg_replace_callback(self::TOKEN_LIENS, fn (): string => $bloc, $contenu);
        } else {
            $corps .= $bloc.$contenu;
        }

        return $this->twig->render('mail/_base.html.twig', ['contenu' => $corps]);
    }

    /** @param array{intro: string, items: array<string, string>}|null $liens */
    private function blocLiens(?array $liens): string
    {
        if (null === $liens || [] === $liens['items']) {
            return '';
        }

        $html = '<p>'.$this->echapper($liens['intro']).'</p><ul>';
        foreach ($liens['items'] as $libelle => $url) {
            $html .= '<li><a href="'.$this->echapper($url).'">'.$this->echapper($libelle).'</a></li>';
        }

        return $html.'</ul>';
    }

    /** @param array<string, string> $variables */
    private function substituer(string $contenu, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $correspondance): string => array_key_exists($correspondance[1], $variables)
                ? $this->echapper($variables[$correspondance[1]])
                : $correspondance[0],
            $contenu,
        );
    }

    private function echapper(string $texte): string
    {
        return htmlspecialchars($texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
