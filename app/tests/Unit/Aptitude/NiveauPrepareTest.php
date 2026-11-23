<?php

namespace App\Tests\Unit\Aptitude;

use App\Aptitude\NiveauPrepare;
use App\Entity\Aptitude;
use PHPUnit\Framework\TestCase;

class NiveauPrepareTest extends TestCase
{
    public function testLePlanningAfficheLeNiveauPrepareAvecLePrefixeP(): void
    {
        $this->assertSame('PPE40', (new Aptitude())->setCode('N1')->getCodePlanning());
        $this->assertSame('PN2', (new Aptitude())->setCode('PE40')->getCodePlanning());
        $this->assertSame('PPE60', NiveauPrepare::libellePlanning('N2'));
        $this->assertSame('PN1', NiveauPrepare::libellePlanning('PN1'));
    }

    public function testUnCodeSansNiveauSuivantResteInchange(): void
    {
        $this->assertNull(NiveauPrepare::de('E4'));
        $this->assertSame('E4', NiveauPrepare::libellePlanning('E4'));
    }
}
