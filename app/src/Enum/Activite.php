<?php

namespace App\Enum;

enum Activite: string
{
    case Apnee = 'APNEE';
    case Pmt = 'PMT';
    case Bloc = 'BLOC';
    case Natation = 'NATATION';
    case Libre = 'LIBRE';
    case Codir = 'CODIR';
    case Cours = 'COURS';

    public function label(): string
    {
        return match ($this) {
            self::Apnee => 'Apnée',
            self::Pmt => 'PMT',
            self::Bloc => 'Bloc',
            self::Natation => 'Natation',
            self::Libre => 'Libre',
            self::Codir => 'CODIR',
            self::Cours => 'Cours',
        };
    }
}
