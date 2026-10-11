<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Introduit l'entité LigneEau (lieu + numéro) et migre le champ texte libre
 * planning_creneau.lignes (ex. "6/5") vers une relation ManyToMany structurée.
 */
final class Version20261010140500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Table 'ligne_eau' (remplace le champ texte libre planning_creneau.lignes)";
    }

    public function up(Schema $schema): void
    {
        // Exécutées immédiatement (et non via addSql(), qui est différé après le retour de up())
        // car la boucle de migration de données ci-dessous a besoin que ces tables existent déjà.
        $this->connection->executeStatement('CREATE TABLE ligne_eau (id INT AUTO_INCREMENT NOT NULL, lieu VARCHAR(20) NOT NULL, numero INT NOT NULL, UNIQUE INDEX UNIQ_3F3CE2E0_LIEU_NUMERO (lieu, numero), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->connection->executeStatement('CREATE TABLE planning_creneau_ligne_eau (planning_creneau_id INT NOT NULL, ligne_eau_id INT NOT NULL, INDEX IDX_C4E1B2A12514723F (planning_creneau_id), INDEX IDX_C4E1B2A1F5A08FC7 (ligne_eau_id), PRIMARY KEY (planning_creneau_id, ligne_eau_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->connection->executeStatement('ALTER TABLE planning_creneau_ligne_eau ADD CONSTRAINT FK_C4E1B2A12514723F FOREIGN KEY (planning_creneau_id) REFERENCES planning_creneau (id) ON DELETE CASCADE');
        $this->connection->executeStatement('ALTER TABLE planning_creneau_ligne_eau ADD CONSTRAINT FK_C4E1B2A1F5A08FC7 FOREIGN KEY (ligne_eau_id) REFERENCES ligne_eau (id) ON DELETE CASCADE');

        // Migration best-effort du texte libre existant (ex. "6/5", parfois une plage "1-4").
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, lieu, lignes FROM planning_creneau WHERE lignes IS NOT NULL AND lignes <> ''"
        );
        foreach ($rows as $row) {
            if (str_contains((string) $row['lignes'], '-')) {
                $this->write(sprintf('Créneau #%d : lignes="%s" contient un tiret (plage potentielle), à vérifier manuellement après migration.', $row['id'], $row['lignes']));
            }

            preg_match_all('/\d+/', (string) $row['lignes'], $matches);
            foreach (array_unique($matches[0]) as $numero) {
                $ligneId = $this->connection->fetchOne(
                    'SELECT id FROM ligne_eau WHERE lieu = ? AND numero = ?',
                    [$row['lieu'], (int) $numero]
                );
                if (!$ligneId) {
                    $this->connection->insert('ligne_eau', ['lieu' => $row['lieu'], 'numero' => (int) $numero]);
                    $ligneId = $this->connection->lastInsertId();
                }
                $this->connection->insert('planning_creneau_ligne_eau', [
                    'planning_creneau_id' => $row['id'],
                    'ligne_eau_id' => $ligneId,
                ]);
            }
        }

        $this->connection->executeStatement('ALTER TABLE planning_creneau DROP lignes');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_creneau ADD lignes VARCHAR(30) DEFAULT NULL');
        $this->addSql("UPDATE planning_creneau pc SET lignes = (
            SELECT GROUP_CONCAT(le.numero ORDER BY le.numero SEPARATOR '/')
            FROM planning_creneau_ligne_eau pcl
            JOIN ligne_eau le ON le.id = pcl.ligne_eau_id
            WHERE pcl.planning_creneau_id = pc.id
        )");

        $this->addSql('ALTER TABLE planning_creneau_ligne_eau DROP FOREIGN KEY FK_C4E1B2A12514723F');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau DROP FOREIGN KEY FK_C4E1B2A1F5A08FC7');
        $this->addSql('DROP TABLE planning_creneau_ligne_eau');
        $this->addSql('DROP TABLE ligne_eau');
    }
}
