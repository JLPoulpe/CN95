<?php

namespace App\Tests\Unit\Mail;

use App\Mail\ContenuMailSanitizer;
use PHPUnit\Framework\TestCase;

class ContenuMailSanitizerTest extends TestCase
{
    public function testConserveLaMiseEnFormeDeLEditeur(): void
    {
        $html = (new ContenuMailSanitizer())->nettoyer('<p class="ql-align-center" style="color: rgb(255, 0, 0);"><strong>Gras</strong> <a href="https://www.cn95plongee.fr/">site</a></p><ol><li>Un</li></ol>');

        $this->assertStringContainsString('<strong>Gras</strong>', $html);
        $this->assertStringContainsString('style="color: rgb(255, 0, 0);"', $html);
        $this->assertStringContainsString('class="ql-align-center"', $html);
        $this->assertStringContainsString('href="https://www.cn95plongee.fr/"', $html);
        $this->assertStringContainsString('<ol>', $html);
    }

    public function testRetireScriptsEtImages(): void
    {
        $html = (new ContenuMailSanitizer())->nettoyer('<p>Bonjour</p><script>alert(1)</script><img src="x.png" onerror="alert(1)">');

        $this->assertStringContainsString('Bonjour', $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('x.png', $html, 'Aucune source d\'image ne doit subsister');
        $this->assertStringNotContainsString('onerror', $html);
    }

    public function testRetireLesLiensJavascript(): void
    {
        $html = (new ContenuMailSanitizer())->nettoyer('<a href="javascript:alert(1)">clic</a>');

        $this->assertStringNotContainsString('javascript:', $html);
    }

    public function testStyleRestreintAuxCouleursEtALignement(): void
    {
        $s = new ContenuMailSanitizer();
        $html = $s->nettoyer('<p style="position:fixed; top:0; left:0; width:100vw; height:100vh; background: url(http://x/y.png); color: #ff0000; background-color: rgb(0, 0, 255); text-align: center">Texte</p>');

        $this->assertStringContainsString('style="color: #ff0000; background-color: rgb(0, 0, 255); text-align: center;"', $html);
        $this->assertStringNotContainsString('position', $html);
        $this->assertStringNotContainsString('url(', $html);
        $this->assertStringNotContainsString('100vw', $html);
    }

    public function testStyleSansRienDAutoriseEstSupprime(): void
    {
        $html = (new ContenuMailSanitizer())->nettoyer('<p style="position:absolute;color:expression(alert(1))">Texte</p>');

        $this->assertStringNotContainsString('style', $html);
        $this->assertStringContainsString('Texte', $html);
    }
}
