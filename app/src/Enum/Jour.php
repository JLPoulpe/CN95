<?php

namespace App\Enum;

enum Jour: string
{
    case Lundi = 'LUNDI';
    case Mardi = 'MARDI';
    case Mercredi = 'MERCREDI';
    case Jeudi = 'JEUDI';
    case Vendredi = 'VENDREDI';
    case Samedi = 'SAMEDI';
    case Dimanche = 'DIMANCHE';

    public function label(): string
    {
        return match ($this) {
            self::Lundi => 'Lundi',
            self::Mardi => 'Mardi',
            self::Mercredi => 'Mercredi',
            self::Jeudi => 'Jeudi',
            self::Vendredi => 'Vendredi',
            self::Samedi => 'Samedi',
            self::Dimanche => 'Dimanche',
        };
    }

    /** Horaire des séances : mercredi et vendredi uniquement, aucune heure les autres jours. */
    public function horaire(): ?string
    {
        return match ($this) {
            self::Mercredi => '19h45 – 21h00',
            self::Vendredi => '21h00 – 22h30',
            default => null,
        };
    }

    /** Décalage en jours depuis le lundi de la semaine. */
    public function decalage(): int
    {
        return array_search($this, self::cases(), true);
    }

    /** @param string|int $n Numéro ISO du jour (1 = lundi … 7 = dimanche) */
    public static function depuisNumeroIso(string|int $n): self
    {
        return self::cases()[((int) $n) - 1];
    }
}
