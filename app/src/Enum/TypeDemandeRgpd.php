<?php

namespace App\Enum;

enum TypeDemandeRgpd: string
{
    /** Accusé de lecture de la mention d'information (obligatoire pour la gestion du planning). */
    case Information = 'INFORMATION';

    /** Ancien choix accepter / refuser les communications facultatives. N'est plus créé ni lu : conservé pour les lignes existantes (purge annuelle). */
    case Communication = 'COMMUNICATION';
}
