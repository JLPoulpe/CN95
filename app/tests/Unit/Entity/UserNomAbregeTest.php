<?php

namespace App\Tests\Unit\Entity;

use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserNomAbregeTest extends TestCase
{
    public function testNomLongTronque(): void
    {
        $u = (new User())->setPrenom('Jean')->setNom('Dupont');
        $this->assertSame('Jean Dup.', $u->getNomAbrege());
    }

    public function testNomCourtInchange(): void
    {
        $u = (new User())->setPrenom('Li')->setNom('Wu');
        $this->assertSame('Li Wu', $u->getNomAbrege());
    }

    public function testAccentsEtNomExactementTroisLettres(): void
    {
        $this->assertSame('Éva Ébe.', (new User())->setPrenom('Éva')->setNom('Ébert')->getNomAbrege());
        $this->assertSame('Ana Roy', (new User())->setPrenom('Ana')->setNom('Roy')->getNomAbrege());
    }
}
