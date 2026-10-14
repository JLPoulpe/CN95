<?php

namespace App\Planning;

use App\Entity\PlanningSemaine;
use App\Enum\Jour;
use App\Enum\Lieu;

/** Regroupe les créneaux d'une semaine par jour puis par lieu, dans l'ordre des enums. */
final class VuePlanning
{
    /** @return array<string, array{jour: Jour, date: \DateTimeImmutable, lieux: array<string, array{lieu: Lieu, creneaux: list<\App\Entity\PlanningCreneau>}>}> */
    public function jours(?PlanningSemaine $semaine, \DateTimeImmutable $lundi): array
    {
        $jours = [];
        if (null === $semaine || $semaine->isFermee()) {
            return $jours;
        }

        foreach (Jour::cases() as $jour) {
            foreach (Lieu::cases() as $lieu) {
                $liste = $semaine->getCreneaux()->filter(fn ($c) => $c->getJour() === $jour && $c->getLieu() === $lieu)->getValues();
                if ($liste) {
                    $jours[$jour->value]['lieux'][$lieu->value] = ['lieu' => $lieu, 'creneaux' => $liste];
                }
            }
            if (isset($jours[$jour->value])) {
                $jours[$jour->value]['jour'] = $jour;
                $jours[$jour->value]['date'] = $lundi->modify('+'.$jour->decalage().' days');
            }
        }

        return $jours;
    }
}
