<?php

namespace App\Aptitude;

/** Progression des niveaux : le niveau préparé par un adhérent qui possède un niveau donné. */
final class NiveauPrepare
{
    public const SUIVANT = [
        'PN1' => 'N1',
        'N1' => 'PE40',
        'PE40' => 'N2',
        'N2' => 'PE60',
        'PE60' => 'N3',
        'N3' => 'N4',
        'N4' => 'N5',
        'E1' => 'E2',
        'E2' => 'E3',
        'E3' => 'E4',
        'MF1' => 'MF2',
    ];

    /** Niveau préparé par les membres du groupe, ou null si aucun niveau ne suit. */
    public static function de(string $code): ?string
    {
        return self::SUIVANT[$code] ?? null;
    }

    /** Libellé du planning : « P » + niveau préparé (N1 → PPE40). Un code sans niveau suivant reste tel quel. */
    public static function libellePlanning(string $code): string
    {
        $prepare = self::de($code);

        return null === $prepare ? $code : 'P'.$prepare;
    }
}
