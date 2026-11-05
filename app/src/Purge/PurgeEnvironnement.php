<?php

namespace App\Purge;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Purge de la base d'un environnement, à deux niveaux :
 *
 * - {@see self::NIVEAU_TOTALE} : tout est supprimé sauf les administrateurs, les rôles et leurs permissions,
 *   et la configuration des mails. Les aptitudes et les lieux d'activité sont supprimés aussi.
 * - {@see self::NIVEAU_CONFIGURATION} : tout est supprimé sauf les administrateurs et tout ce qui se trouve dans
 *   « Configuration de l'outil » (aptitudes, lieux d'activité, configuration des mails, permissions des rôles).
 *
 * Les administrateurs ne sont jamais supprimés : sans eux, plus personne ne pourrait se connecter.
 *
 * Chaque table de la base doit être classée dans une des listes ci-dessous. Une table inconnue fait échouer la purge
 * (aucune suppression) : une nouvelle table de configuration ne doit pas être effacée par erreur.
 */
final class PurgeEnvironnement
{
    public const NIVEAU_TOTALE = 'totale';
    public const NIVEAU_CONFIGURATION = 'configuration';
    public const NIVEAUX = [self::NIVEAU_TOTALE, self::NIVEAU_CONFIGURATION];

    public const ROLE_ADMIN = 'ADMIN';

    /** Conservées dans les deux niveaux. */
    private const TABLES_CONSERVEES = [
        'doctrine_migration_versions',
        'role',
        'role_permission',
        'mail_configuration',
    ];

    /** Conservées au niveau « configuration » uniquement (« Configuration de l'outil »). */
    private const TABLES_CONFIGURATION_OUTIL = [
        'aptitude',
        'lieu_activite',
    ];

    /** Tables de données purgées entièrement dans les deux niveaux. */
    private const TABLES_DONNEES = [
        'planning_creneau_aptitude',
        'planning_creneau_activite',
        'planning_creneau_lieu_activite',
        'planning_creneau',
        'planning_semaine',
        'palanquee_membre',
        'palanquee',
        'fiche_securite',
        'reponse_fosse',
        'invitation_fosse_aptitude',
        'invitation_fosse_role',
        'invitation_fosse',
        'activite_externe',
        'activite',
        'communication',
        'demande_rgpd',
        'messenger_messages',
    ];

    /** Tables liées aux utilisateurs : seuls les administrateurs sont conservés. */
    private const TABLES_UTILISATEURS = [
        'user',
        'user_role',
        'user_aptitude_encadree',
    ];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return list<array{libelle: string, nombre: int}> Nombre de lignes supprimées (ou qui le seraient) par étape
     *
     * @throws PurgeException
     */
    public function executer(string $niveau, bool $simulation): array
    {
        if (!in_array($niveau, self::NIVEAUX, true)) {
            throw new PurgeException(sprintf('Niveau de purge inconnu : %s.', $niveau));
        }

        $this->verifierTablesClassees();

        $admins = $this->idsAdministrateurs();
        if ([] === $admins) {
            throw new PurgeException('Aucun compte administrateur : purge annulée pour ne pas bloquer l\'accès à l\'outil.');
        }

        $etapes = $this->etapes($niveau);
        $resultat = [];
        foreach ($etapes as $etape) {
            $nombre = $this->compter($etape['table'], $etape['condition'], $admins);
            $resultat[] = ['libelle' => $etape['libelle'], 'nombre' => $nombre];
        }

        if ($simulation) {
            return $resultat;
        }

        $this->connection->beginTransaction();
        try {
            foreach ($etapes as $etape) {
                if ($etape['detacherAdministrateurs']) {
                    $this->connection->executeStatement(
                        'UPDATE `user` SET aptitude_id = NULL, aptitude_preparee_id = NULL WHERE id IN (:admins)',
                        ['admins' => $admins],
                        ['admins' => ArrayParameterType::INTEGER],
                    );
                }
                $this->supprimer($etape['table'], $etape['condition'], $admins);
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }

        return $resultat;
    }

    /**
     * Étapes dans l'ordre de suppression : les tables qui pointent vers d'autres sont vidées en premier.
     *
     * @return list<array{libelle: string, table: string, condition: ?string, detacherAdministrateurs: bool}>
     */
    private function etapes(string $niveau): array
    {
        $totale = self::NIVEAU_TOTALE === $niveau;
        $etapes = [];

        $libelles = [
            'planning_creneau_aptitude' => 'Planning : aptitudes des créneaux',
            'planning_creneau_activite' => 'Planning : activités des créneaux',
            'planning_creneau_lieu_activite' => 'Planning : lieux des créneaux',
            'planning_creneau' => 'Planning : créneaux',
            'planning_semaine' => 'Planning : semaines',
            'palanquee_membre' => 'Fiches de sécurité : membres des palanquées',
            'palanquee' => 'Fiches de sécurité : palanquées',
            'fiche_securite' => 'Fiches de sécurité',
            'reponse_fosse' => 'Réponses aux invitations',
            'invitation_fosse_aptitude' => 'Invitations : aptitudes',
            'invitation_fosse_role' => 'Invitations : rôles',
            'invitation_fosse' => 'Invitations',
            'activite_externe' => 'Activités externes',
            'activite' => 'Activités du planning',
            'communication' => 'Communications',
            'demande_rgpd' => 'Demandes RGPD',
            'messenger_messages' => 'File d\'envoi des mails',
        ];
        foreach (self::TABLES_DONNEES as $table) {
            $etapes[] = $this->etape($libelles[$table], $table, null);
        }

        // Utilisateurs : on garde les administrateurs et eux seuls
        $etapes[] = $this->etape(
            'Encadrement des aptitudes',
            'user_aptitude_encadree',
            $totale ? null : 'user_id NOT IN (:admins)',
        );
        $etapes[] = $this->etape('Rôles des utilisateurs (hors administrateurs)', 'user_role', 'user_id NOT IN (:admins)');
        $etapes[] = $this->etape('Utilisateurs (hors administrateurs)', 'user', 'id NOT IN (:admins)');

        if ($totale) {
            // L'administrateur ne doit plus pointer vers une aptitude supprimée
            $etapes[] = $this->etape('Aptitudes', 'aptitude', null, detacherAdministrateurs: true);
            $etapes[] = $this->etape('Lieux d\'activité', 'lieu_activite', null);
        }

        return $etapes;
    }

    /** @return array{libelle: string, table: string, condition: ?string, detacherAdministrateurs: bool} */
    private function etape(string $libelle, string $table, ?string $condition, bool $detacherAdministrateurs = false): array
    {
        return [
            'libelle' => $libelle,
            'table' => $table,
            'condition' => $condition,
            'detacherAdministrateurs' => $detacherAdministrateurs,
        ];
    }

    /** @return list<int> */
    private function idsAdministrateurs(): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT ur.user_id FROM user_role ur INNER JOIN `role` r ON r.id = ur.role_id WHERE r.code = :admin',
            ['admin' => self::ROLE_ADMIN],
        ));
    }

    /** @param list<int> $admins */
    private function compter(string $table, ?string $condition, array $admins): int
    {
        $sql = sprintf('SELECT COUNT(*) FROM %s', $this->identifiant($table));
        $params = [];
        $types = [];
        if (null !== $condition) {
            $sql .= ' WHERE '.$condition;
            $params['admins'] = $admins;
            $types['admins'] = ArrayParameterType::INTEGER;
        }

        return (int) $this->connection->fetchOne($sql, $params, $types);
    }

    /** @param list<int> $admins */
    private function supprimer(string $table, ?string $condition, array $admins): void
    {
        $sql = sprintf('DELETE FROM %s', $this->identifiant($table));
        $params = [];
        $types = [];
        if (null !== $condition) {
            $sql .= ' WHERE '.$condition;
            $params['admins'] = $admins;
            $types['admins'] = ArrayParameterType::INTEGER;
        }

        $this->connection->executeStatement($sql, $params, $types);
    }

    private function identifiant(string $table): string
    {
        return $this->connection->quoteIdentifier($table);
    }

    /** @throws PurgeException si une table de la base n'est classée nulle part */
    private function verifierTablesClassees(): void
    {
        $connues = array_merge(
            self::TABLES_CONSERVEES,
            self::TABLES_CONFIGURATION_OUTIL,
            self::TABLES_DONNEES,
            self::TABLES_UTILISATEURS,
        );

        $inconnues = array_values(array_diff(
            $this->connection->createSchemaManager()->listTableNames(),
            $connues,
        ));
        if ([] !== $inconnues) {
            throw new PurgeException(sprintf(
                'Tables non classées dans la purge : %s. Ajouter chaque table à une liste de PurgeEnvironnement avant de relancer.',
                implode(', ', $inconnues),
            ));
        }
    }
}
