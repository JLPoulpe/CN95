<?php

namespace App\Enum;

enum TypeDemandeRgpd: string
{
    /** Accusé de lecture de la mention d'information (obligatoire pour la gestion du planning). */
    case Information = 'INFORMATION';

    /** Choix accepter / refuser les communications non liées au planning (consentement). */
    case Communication = 'COMMUNICATION';
}
