<?php

namespace App\Security;

use App\Entity\Role;

/**
 * Droits fonctionnels de l'espace administrateur.
 * Les rôles qui accordent chaque permission sont enregistrés en base (table role_permission) et se modifient depuis
 * l'écran Permissions de l'administration ; DEFAUTS ne sert qu'à initialiser la base. Un ADMIN a toujours tous les droits.
 */
final class Permission
{
    public const INVITATIONS = 'PERM_INVITATIONS';
    public const COMMUNICATION = 'PERM_COMMUNICATION';
    public const ACTIVITES_EXTERNES = 'PERM_ACTIVITES_EXTERNES';
    public const UTILISATEURS = 'PERM_UTILISATEURS';
    public const CONFIG_ACTIVITES = 'PERM_CONFIG_ACTIVITES';
    public const CONFIG_OUTIL = 'PERM_CONFIG_OUTIL';

    /** Libellé affiché dans l'écran des permissions. */
    public const LIBELLES = [
        self::INVITATIONS => 'Invitations',
        self::COMMUNICATION => 'Communications',
        self::ACTIVITES_EXTERNES => 'Activités externes',
        self::UTILISATEURS => 'Gestion des utilisateurs',
        self::CONFIG_ACTIVITES => 'Configuration des activités',
        self::CONFIG_OUTIL => 'Configuration de l\'outil',
    ];

    /** Configuration initiale : codes de rôles (hors ADMIN) accordant chaque permission. */
    public const DEFAUTS = [
        self::INVITATIONS => [Role::BUREAU],
        self::COMMUNICATION => [Role::BUREAU],
        self::ACTIVITES_EXTERNES => [Role::BUREAU, Role::MONITEUR],
        self::UTILISATEURS => [],
        self::CONFIG_ACTIVITES => [Role::BUREAU],
        self::CONFIG_OUTIL => [],
    ];

    /** @return list<string> */
    public static function toutes(): array
    {
        return array_keys(self::LIBELLES);
    }
}
