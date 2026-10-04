<?php

namespace App\Enum;

enum Lieu: string
{
    case PetitBassin = 'PETIT_BASSIN';
    case GrandBassin = 'GRAND_BASSIN';
    case Bureau = 'BUREAU';
    case GrandeSalle = 'GRANDE_SALLE';

    public function label(): string
    {
        return match ($this) {
            self::PetitBassin => 'Petit bassin',
            self::GrandBassin => 'Grand bassin',
            self::Bureau => 'Bureau',
            self::GrandeSalle => 'Grande salle',
        };
    }

    public function aDesLignes(): bool
    {
        return $this === self::PetitBassin || $this === self::GrandBassin;
    }
}
