<?php

namespace App\Tests\Unit\Mail;

use App\Entity\MailTemplate;
use App\Enum\TypeMailTemplate;
use App\Mail\ContenuMailSanitizer;
use App\Mail\MailTemplateRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class MailTemplateRendererTest extends TestCase
{
    private function renderer(): MailTemplateRenderer
    {
        // Mise en page réduite : le test porte sur l'assemblage, pas sur le design de _base.html.twig
        return new MailTemplateRenderer(new Environment(new ArrayLoader([
            'mail/_base.html.twig' => '<body>{{ contenu|raw }}</body>',
        ])), new ContenuMailSanitizer());
    }

    private function template(string $contenu): MailTemplate
    {
        return (new MailTemplate())->setNom('Test')->setType(TypeMailTemplate::Invitation)->setContenu($contenu);
    }

    public function testRemplaceLesVariables(): void
    {
        $html = $this->renderer()->rendre(
            $this->template('<p>Fosse {{activite}} du {{ date }}, {{prenom}} {{nom}}</p>'),
            ['activite' => 'Fosse', 'date' => '19/10/2026', 'prenom' => 'Julien', 'nom' => 'Ledieu'],
        );

        $this->assertStringContainsString('<p>Fosse Fosse du 19/10/2026, Julien Ledieu</p>', $html);
    }

    public function testLaissePlaceInconnueTelQuelle(): void
    {
        $html = $this->renderer()->rendre($this->template('<p>{{inconnue}}</p>'));

        $this->assertStringContainsString('<p>{{inconnue}}</p>', $html);
    }

    public function testEchappeLesValeurs(): void
    {
        $html = $this->renderer()->rendre(
            $this->template('<p>{{activite}}</p>'),
            ['activite' => '<script>alert(1)</script>'],
        );

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testCommentaireTexteBrutEchappeEtPlacéAvantLeContenu(): void
    {
        $html = $this->renderer()->rendre($this->template('<p>CORPS</p>'), [], "Ligne 1\nA & B");

        $this->assertStringContainsString('<p>Ligne 1<br />'."\n".'A &amp; B</p>', $html);
        $this->assertLessThan(strpos($html, 'CORPS'), strpos($html, 'Ligne 1'));
    }

    public function testCommentaireHtmlNettoyé(): void
    {
        $html = $this->renderer()->rendre($this->template('<p>CORPS</p>'), [], '<p><strong>Gras</strong></p><script>x()</script>');

        $this->assertStringContainsString('<p><strong>Gras</strong></p>', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function testBlocDeLiensAjouteAvantLeContenu(): void
    {
        $html = $this->renderer()->rendre($this->template('<p>CORPS</p>'), [], null, [
            'intro' => 'Merci d\'indiquer votre choix :',
            'items' => ['Je m\'inscris' => 'https://example.test/inscription'],
        ]);

        $this->assertStringContainsString('<p>Merci d&#039;indiquer votre choix :</p><ul><li><a href="https://example.test/inscription">Je m&#039;inscris</a></li></ul>', $html);
        $this->assertLessThan(strpos($html, 'CORPS'), strpos($html, 'Je m&#039;inscris'));
    }

    public function testPasDeBlocDeLiensSansLiens(): void
    {
        $html = $this->renderer()->rendre($this->template('<p>CORPS</p>'), [], null, ['intro' => 'Intro', 'items' => []]);

        $this->assertStringNotContainsString('<ul>', $html);
        $this->assertStringNotContainsString('Intro', $html);
    }

    public function testBlocDeLiensPlacéALEmplacementDuToken(): void
    {
        $html = $this->renderer()->rendre($this->template('<p>AVANT</p>{{liens}}<p>APRES</p>'), [], null, [
            'intro' => 'Intro',
            'items' => ['Lien' => 'https://example.test/x'],
        ]);

        $this->assertLessThan(strpos($html, 'Intro'), strpos($html, 'AVANT'));
        $this->assertLessThan(strpos($html, 'APRES'), strpos($html, 'Intro'));
        $this->assertStringNotContainsString('{{liens}}', $html);
    }

    public function testTokenLiensSansLiensNeLaisseRien(): void
    {
        $html = $this->renderer()->rendre($this->template('<p>AVANT</p>{{liens}}<p>APRES</p>'));

        $this->assertStringNotContainsString('{{liens}}', $html);
        $this->assertStringNotContainsString('<ul>', $html);
    }
}
