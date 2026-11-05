<?php

namespace App\Tests\Unit;

use App\Entity\LieuActivite;
use App\Entity\PlanningCreneau;
use App\Entity\PlanningSemaine;
use App\Enum\Jour;
use App\Planning\VuePlanning;
use PHPUnit\Framework\TestCase;

class LieuxCreneauTest extends TestCase
{
    private function lieu(string $nom, string $groupe, int $ordre): LieuActivite
    {
        return (new LieuActivite())->setNom($nom)->setGroupe($groupe)->setOrdre($ordre);
    }

    public function testLibelleEtGroupesDuCreneau(): void
    {
        $c = (new PlanningCreneau())
            ->addLieu($this->lieu('Bureau', 'Salles', 100))
            ->addLieu($this->lieu('Ligne 2', 'Petit bassin', 12))
            ->addLieu($this->lieu('Ligne 1', 'Petit bassin', 11));

        $this->assertSame(['Petit bassin', 'Salles'], $c->getGroupes());
        $this->assertSame('Petit bassin : Ligne 2, Ligne 1 · Salles : Bureau', $c->getLieuxLabel());
    }

    public function testCreneauMultiGroupesApparaitDansChaqueGroupe(): void
    {
        $semaine = new PlanningSemaine();
        $c = (new PlanningCreneau())->setJour(Jour::Lundi)
            ->addLieu($this->lieu('Ligne 1', 'Grand bassin', 21))
            ->addLieu($this->lieu('Grande salle', 'Salles', 100));
        $semaine->addCreneau($c);

        $jours = (new VuePlanning())->jours($semaine, new \DateTimeImmutable('2026-11-02'));

        $this->assertSame(['Grand bassin', 'Salles'], array_keys($jours['LUNDI']['lieux']));
        $this->assertSame([$c], $jours['LUNDI']['lieux']['Salles']['creneaux']);
    }
}
