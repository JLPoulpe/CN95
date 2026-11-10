<?php

namespace App\Export;

/**
 * Neutralise l'injection de formules dans les exports CSV : une cellule qui commence par = + - @ (ou tabulation,
 * retour chariot) est interprétée comme une formule par Excel / LibreOffice. Les données saisies par les membres
 * (nom, prénom, téléphone) ne doivent jamais s'exécuter chez le moniteur qui ouvre le fichier.
 */
final class CsvSecurise
{
    private const DECLENCHEURS = ['=', '+', '-', '@', "\t", "\r", "\n"];

    public static function cellule(int|string|null $valeur): int|string
    {
        if (!\is_string($valeur)) {
            return $valeur ?? '';
        }

        // Les espaces de tête sont ignorés par certains tableurs avant l'interprétation de la formule
        $debut = ltrim($valeur, " \u{00A0}");
        if ('' !== $debut && \in_array($debut[0], self::DECLENCHEURS, true)) {
            return "'".$valeur;
        }

        return $valeur;
    }

    /**
     * @param array<int, int|string|null> $champs
     *
     * @return array<int, int|string>
     */
    public static function ligne(array $champs): array
    {
        return array_map(self::cellule(...), $champs);
    }
}
