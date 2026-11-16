<?php

namespace App\Tests\Unit\Entity;

use App\Entity\InvitationFosse;
use App\Entity\ReponseFosse;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class ReponseFosseTest extends TestCase
{
    private function reponse(?int $ordre, int $id): ReponseFosse
    {
        $reponse = (new ReponseFosse(new InvitationFosse(), new User()))->setOrdre($ordre);
        (new \ReflectionProperty($reponse, 'id'))->setValue($reponse, $id);

        return $reponse;
    }

    public function testOrdreManuelPasseAvantLOrdreDArrivee(): void
    {
        $sansOrdre = $this->reponse(null, 1);
        $promu = $this->reponse(1, 2);
        $second = $this->reponse(2, 3);

        $liste = [$sansOrdre, $second, $promu];
        usort($liste, ReponseFosse::comparerAttente(...));

        $this->assertSame([2, 3, 1], array_map(fn (ReponseFosse $r) => $r->getId(), $liste));
    }

    public function testSansOrdreManuelOnGardeLOrdreDIdentifiant(): void
    {
        $liste = [$this->reponse(null, 5), $this->reponse(null, 4)];
        usort($liste, ReponseFosse::comparerAttente(...));

        $this->assertSame([4, 5], array_map(fn (ReponseFosse $r) => $r->getId(), $liste));
    }
}
