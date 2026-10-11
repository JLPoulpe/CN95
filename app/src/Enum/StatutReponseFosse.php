<?php

namespace App\Enum;

enum StatutReponseFosse: string
{
    case EnAttente = 'EN_ATTENTE';
    case Inscrit = 'INSCRIT';
    case Refus = 'REFUS';
    case Desistement = 'DESISTEMENT';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'Sans réponse',
            self::Inscrit => 'Inscrit',
            self::Refus => 'Ne participe pas',
            self::Desistement => 'Désistement',
        };
    }
}
