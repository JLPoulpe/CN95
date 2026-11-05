<?php

namespace App\Enum;

enum StatutReponseFosse: string
{
    case EnAttente = 'EN_ATTENTE';
    case Inscrit = 'INSCRIT';
    case ListeAttente = 'LISTE_ATTENTE';
    case Refus = 'REFUS';
    case Desistement = 'DESISTEMENT';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'Sans réponse',
            self::Inscrit => 'Inscrit',
            self::ListeAttente => 'Liste d\'attente',
            self::Refus => 'Ne participe pas',
            self::Desistement => 'Désistement',
        };
    }
}
