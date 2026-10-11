<?php

namespace App\Tests\Unit\Mail;

use App\Mail\MailException;
use App\Mail\SecretBox;
use PHPUnit\Framework\TestCase;

class SecretBoxTest extends TestCase
{
    public function testAllerRetourRestitueLeClair(): void
    {
        $box = new SecretBox('secret-de-test');

        $chiffre = $box->chiffrer('xkeys-123');

        $this->assertNotSame('xkeys-123', $chiffre);
        $this->assertSame('xkeys-123', $box->dechiffrer($chiffre));
    }

    public function testUnAutreSecretNePeutPasDechiffrer(): void
    {
        $chiffre = (new SecretBox('secret-a'))->chiffrer('valeur');

        $this->expectException(MailException::class);
        (new SecretBox('secret-b'))->dechiffrer($chiffre);
    }
}
