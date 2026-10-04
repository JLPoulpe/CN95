<?php

namespace App\Enum;

enum Jour: string
{
    case Lundi = 'LUNDI';
    case Vendredi = 'VENDREDI';

    public const HORAIRE = '21h00 – 22h30';

    public function label(): string
    {
        return match ($this) {
            self::Lundi => 'Lundi',
            self::Vendredi => 'Vendredi',
        };
    }

    /** Décalage en jours depuis le lundi de la semaine. */
    public function decalage(): int
    {
        return match ($this) {
            self::Lundi => 0,
            self::Vendredi => 4,
        };
    }
}
