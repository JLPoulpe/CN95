<?php

namespace App\Rgpd;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Purge annuelle (31/08) : supprime les comptes et les invitations créés jusqu'à la date limite, date de fin de journée incluse.
 *
 * Les comptes administrateurs ne sont pas supprimés : sans eux, plus personne ne pourrait se connecter pour reprendre le site.
 * Requêtes SQL directes plutôt que l'ORM : la purge concerne des lots entiers et doit suivre les clés étrangères telles quelles.
 */
final class PurgeAnnuelle
{
    public const ROLE_EXCLU = 'ADMIN';

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @return array{invitations: int, reponses: int, utilisateurs: int, demandes: int} Nombre d'éléments concernés
     */
    public function executer(\DateTimeImmutable $limite, bool $simulation): array
    {
        $fin = $limite->setTime(23, 59, 59)->format('Y-m-d H:i:s');

        $invitationIds = $this->idsInvitations($fin);
        $userIds = $this->idsUtilisateurs($fin);

        $resultat = [
            'invitations' => count($invitationIds),
            'reponses' => $this->compter(
                'SELECT COUNT(*) FROM reponse_fosse WHERE invitation_id IN (:invitationIds) OR user_id IN (:userIds)',
                ['invitationIds' => $invitationIds, 'userIds' => $userIds],
            ),
            'utilisateurs' => count($userIds),
            'demandes' => $this->compter('SELECT COUNT(*) FROM demande_rgpd WHERE user_id IN (:userIds)', ['userIds' => $userIds]),
        ];

        if ($simulation || ([] === $invitationIds && [] === $userIds)) {
            return $resultat;
        }

        $this->connection->beginTransaction();
        try {
            if ([] !== $invitationIds) {
                $this->supprimer('DELETE FROM invitation_fosse_aptitude WHERE invitation_fosse_id IN (:ids)', $invitationIds);
                $this->supprimer('DELETE FROM invitation_fosse_role WHERE invitation_fosse_id IN (:ids)', $invitationIds);
                $this->supprimer('DELETE FROM reponse_fosse WHERE invitation_id IN (:ids)', $invitationIds);
                $this->supprimer('DELETE FROM invitation_fosse WHERE id IN (:ids)', $invitationIds);
            }

            $this->supprimerUtilisateurs($userIds);

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }

        return $resultat;
    }

    /**
     * Efface toutes les données d'un seul utilisateur (droit à l'effacement demandé par la personne elle-même).
     * Refuse de supprimer le dernier administrateur : sans lui, plus personne ne pourrait reprendre le site.
     *
     * @throws \DomainException si l'utilisateur est le dernier administrateur
     */
    public function supprimerUtilisateur(int $userId): void
    {
        $this->connection->beginTransaction();
        try {
            $administrateurs = array_map('intval', $this->connection->fetchFirstColumn(
                'SELECT ur.user_id FROM user_role ur INNER JOIN `role` r ON r.id = ur.role_id WHERE r.code = :admin',
                ['admin' => self::ROLE_EXCLU],
            ));
            if ([$userId] === $administrateurs) {
                throw new \DomainException('Le dernier administrateur ne peut pas être supprimé.');
            }

            $this->supprimerUtilisateurs([$userId]);
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollBack();

            throw $e;
        }
    }

    /** @param non-empty-list<int> $userIds À appeler dans une transaction */
    private function supprimerUtilisateurs(array $userIds): void
    {
        // Les invitations et plannings conservés ne doivent plus pointer vers un responsable supprimé
        $this->supprimer('UPDATE invitation_fosse SET dp_id = NULL WHERE dp_id IN (:ids)', $userIds);
        $this->supprimer('UPDATE planning_semaine SET dp_id = NULL WHERE dp_id IN (:ids)', $userIds);
        $this->supprimer('UPDATE planning_semaine SET dp_vendredi_id = NULL WHERE dp_vendredi_id IN (:ids)', $userIds);
        $this->supprimer('DELETE FROM reponse_fosse WHERE user_id IN (:ids)', $userIds);
        $this->supprimer('DELETE FROM demande_rgpd WHERE user_id IN (:ids)', $userIds);
        $this->supprimer('DELETE FROM user_role WHERE user_id IN (:ids)', $userIds);
        $this->supprimer('DELETE FROM `user` WHERE id IN (:ids)', $userIds);
    }

    /** @return list<int> */
    private function idsInvitations(string $fin): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT id FROM invitation_fosse WHERE created_at <= :fin',
            ['fin' => $fin],
        ));
    }

    /** @return list<int> */
    private function idsUtilisateurs(string $fin): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT u.id FROM `user` u WHERE u.created_at <= :fin AND u.id NOT IN (
                SELECT ur.user_id FROM user_role ur INNER JOIN `role` r ON r.id = ur.role_id WHERE r.code = :admin
            )',
            ['fin' => $fin, 'admin' => self::ROLE_EXCLU],
        ));
    }

    /**
     * Compte les lignes liées aux listes d'identifiants. Une liste vide est remplacée par [0] (IN () est invalide en SQL)
     * et ne compte rien si toutes les listes sont vides.
     *
     * @param array<string, list<int>> $listes Paramètre SQL => identifiants
     */
    private function compter(string $sql, array $listes): int
    {
        if (array_filter($listes) === []) {
            return 0;
        }

        $types = [];
        foreach ($listes as $nom => $ids) {
            $listes[$nom] = $ids ?: [0];
            $types[$nom] = ArrayParameterType::INTEGER;
        }

        return (int) $this->connection->fetchOne($sql, $listes, $types);
    }

    /** @param list<int> $ids */
    private function supprimer(string $sql, array $ids): void
    {
        $this->connection->executeStatement($sql, ['ids' => $ids], ['ids' => ArrayParameterType::INTEGER]);
    }
}
