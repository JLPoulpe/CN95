<?php

namespace App\Planning;

use App\Entity\PlanningCreneau;
use App\Entity\PlanningSemaine;
use App\Enum\Jour;

/**
 * Regroupe les créneaux d'une semaine par jour puis par groupe de lieux.
 * Un créneau qui occupe plusieurs groupes apparaît dans chacun d'eux.
 */
final class VuePlanning
{
    /** @return array<string, array{jour: Jour, date: \DateTimeImmutable, lieux: array<string, array{groupe: string, creneaux: list<PlanningCreneau>}>}> */
    public function jours(?PlanningSemaine $semaine, \DateTimeImmutable $lundi): array
    {
        $jours = [];
        if (null === $semaine || $semaine->isFermee()) {
            return $jours;
        }

        foreach (Jour::cases() as $jour) {
            $groupes = []; // nom => [ordre, créneaux]
            foreach ($semaine->getCreneaux() as $c) {
                if ($c->getJour() !== $jour) {
                    continue;
                }
                foreach ($c->getLieux() as $l) {
                    $g = $l->getGroupe();
                    $groupes[$g]['ordre'] = min($groupes[$g]['ordre'] ?? PHP_INT_MAX, $l->getOrdre());
                    $groupes[$g]['creneaux'][spl_object_id($c)] = $c;
                }
            }
            if (!$groupes) {
                continue;
            }
            uasort($groupes, fn ($a, $b) => $a['ordre'] <=> $b['ordre']);
            foreach ($groupes as $nom => $g) {
                $jours[$jour->value]['lieux'][$nom] = ['groupe' => $nom, 'creneaux' => array_values($g['creneaux'])];
            }
            $jours[$jour->value]['jour'] = $jour;
            $jours[$jour->value]['date'] = $lundi->modify('+'.$jour->decalage().' days');
        }

        return $jours;
    }
}
