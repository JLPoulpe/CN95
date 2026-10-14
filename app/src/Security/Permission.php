<?php

namespace App\Security;

use App\Entity\Role;

/**
 * Droits fonctionnels de l'espace administrateur.
 * Pour changer qui accède à quoi, il suffit de modifier ROLES. Un ADMIN a toujours tous les droits.
 */
final class Permission
{
    public const INVITATIONS = 'PERM_INVITATIONS';
    public const COMMUNICATION = 'PERM_COMMUNICATION';
    public const ACTIVITES_EXTERNES = 'PERM_ACTIVITES_EXTERNES';
    public const UTILISATEURS = 'PERM_UTILISATEURS';
    public const CONFIG_ACTIVITES = 'PERM_CONFIG_ACTIVITES';
    public const CONFIG_OUTIL = 'PERM_CONFIG_OUTIL';

    /** Codes de rôles (hors ADMIN) accordant chaque permission. */
    public const ROLES = [
        self::INVITATIONS => [Role::BUREAU, Role::MONITEUR],
        self::COMMUNICATION => [Role::BUREAU, Role::MONITEUR],
        self::ACTIVITES_EXTERNES => [Role::BUREAU, Role::MONITEUR],
        self::UTILISATEURS => [],
        self::CONFIG_ACTIVITES => [Role::BUREAU],
        self::CONFIG_OUTIL => [],
    ];

    /** @return list<string> */
    public static function rolesPour(string $permission): array
    {
        return self::ROLES[$permission] ?? [];
    }
}
