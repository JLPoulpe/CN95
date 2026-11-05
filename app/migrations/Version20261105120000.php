<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Lignes d'eau + enum Lieu → lieux d'activité (entité configurable, plusieurs lieux par créneau). */
final class Version20261105120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remplace ligne_eau et planning_creneau.lieu par lieu_activite';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE lieu_activite (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, groupe VARCHAR(100) NOT NULL, ordre INT NOT NULL, UNIQUE INDEX UNIQ_6F9903C34B98C216C6E55B5 (groupe, nom), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planning_creneau_lieu_activite (planning_creneau_id INT NOT NULL, lieu_activite_id INT NOT NULL, INDEX IDX_BF12F240E4A628C9 (planning_creneau_id), INDEX IDX_BF12F24057569565 (lieu_activite_id), PRIMARY KEY (planning_creneau_id, lieu_activite_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE planning_creneau_lieu_activite ADD CONSTRAINT FK_PCLA_CRENEAU FOREIGN KEY (planning_creneau_id) REFERENCES planning_creneau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_creneau_lieu_activite ADD CONSTRAINT FK_PCLA_LIEU FOREIGN KEY (lieu_activite_id) REFERENCES lieu_activite (id) ON DELETE CASCADE');

        // Lignes des bassins : « Ligne N », groupe = libellé du bassin
        $this->addSql("INSERT INTO lieu_activite (nom, groupe, ordre) SELECT CONCAT('Ligne ', numero), IF(lieu = 'PETIT_BASSIN', 'Petit bassin', 'Grand bassin'), IF(lieu = 'PETIT_BASSIN', 10, 20) + numero FROM ligne_eau");
        // Salles et bureau
        $this->addSql("INSERT INTO lieu_activite (nom, groupe, ordre) VALUES ('Grande salle', 'Salles', 100), ('Bureau', 'Salles', 101)");
        // Bassins entiers, pour les créneaux de bassin sans ligne précisée
        $this->addSql("INSERT INTO lieu_activite (nom, groupe, ordre) VALUES ('Bassin entier', 'Petit bassin', 10), ('Bassin entier', 'Grand bassin', 20)");

        // Créneaux avec lignes
        $this->addSql("INSERT INTO planning_creneau_lieu_activite (planning_creneau_id, lieu_activite_id) SELECT j.planning_creneau_id, la.id FROM planning_creneau_ligne_eau j JOIN ligne_eau le ON le.id = j.ligne_eau_id JOIN lieu_activite la ON la.nom = CONCAT('Ligne ', le.numero) AND la.groupe = IF(le.lieu = 'PETIT_BASSIN', 'Petit bassin', 'Grand bassin')");
        // Créneaux sans ligne : le lieu d'origine devient le lieu d'activité
        $this->addSql("INSERT INTO planning_creneau_lieu_activite (planning_creneau_id, lieu_activite_id) SELECT c.id, la.id FROM planning_creneau c JOIN lieu_activite la ON (c.lieu = 'GRANDE_SALLE' AND la.nom = 'Grande salle' AND la.groupe = 'Salles') OR (c.lieu = 'BUREAU' AND la.nom = 'Bureau' AND la.groupe = 'Salles') OR (c.lieu = 'PETIT_BASSIN' AND la.nom = 'Bassin entier' AND la.groupe = 'Petit bassin') OR (c.lieu = 'GRAND_BASSIN' AND la.nom = 'Bassin entier' AND la.groupe = 'Grand bassin') WHERE NOT EXISTS (SELECT 1 FROM planning_creneau_ligne_eau j WHERE j.planning_creneau_id = c.id)");

        // « Bassin entier » inutilisé : on ne pollue pas la configuration
        $this->addSql("DELETE la FROM lieu_activite la WHERE la.nom = 'Bassin entier' AND NOT EXISTS (SELECT 1 FROM planning_creneau_lieu_activite p WHERE p.lieu_activite_id = la.id)");

        $this->addSql('ALTER TABLE planning_creneau DROP lieu');
        $this->addSql('DROP TABLE planning_creneau_ligne_eau');
        $this->addSql('DROP TABLE ligne_eau');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE ligne_eau (id INT AUTO_INCREMENT NOT NULL, lieu VARCHAR(20) NOT NULL, numero INT NOT NULL, UNIQUE INDEX UNIQ_3F3CF0982F577D59F55AE19E (lieu, numero), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planning_creneau_ligne_eau (planning_creneau_id INT NOT NULL, ligne_eau_id INT NOT NULL, INDEX IDX_F43843ACE4A628C9 (planning_creneau_id), INDEX IDX_F43843ACAE92B17D (ligne_eau_id), PRIMARY KEY (planning_creneau_id, ligne_eau_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau ADD CONSTRAINT FK_F43843ACE4A628C9 FOREIGN KEY (planning_creneau_id) REFERENCES planning_creneau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau ADD CONSTRAINT FK_F43843ACAE92B17D FOREIGN KEY (ligne_eau_id) REFERENCES ligne_eau (id) ON DELETE CASCADE');
        $this->addSql("ALTER TABLE planning_creneau ADD lieu VARCHAR(20) NOT NULL DEFAULT 'PETIT_BASSIN'");

        // Best-effort : lignes numérotées, lieu = premier groupe du créneau
        $this->addSql("INSERT INTO ligne_eau (lieu, numero) SELECT DISTINCT IF(groupe = 'Petit bassin', 'PETIT_BASSIN', 'GRAND_BASSIN'), CAST(REPLACE(nom, 'Ligne ', '') AS UNSIGNED) FROM lieu_activite WHERE groupe IN ('Petit bassin', 'Grand bassin') AND nom REGEXP '^Ligne [0-9]+$'");
        $this->addSql("INSERT INTO planning_creneau_ligne_eau (planning_creneau_id, ligne_eau_id) SELECT p.planning_creneau_id, le.id FROM planning_creneau_lieu_activite p JOIN lieu_activite la ON la.id = p.lieu_activite_id JOIN ligne_eau le ON le.lieu = IF(la.groupe = 'Petit bassin', 'PETIT_BASSIN', 'GRAND_BASSIN') AND CONCAT('Ligne ', le.numero) = la.nom");
        $this->addSql("UPDATE planning_creneau c SET c.lieu = COALESCE((SELECT CASE la.groupe WHEN 'Grand bassin' THEN 'GRAND_BASSIN' WHEN 'Petit bassin' THEN 'PETIT_BASSIN' WHEN 'Salles' THEN IF(la.nom = 'Bureau', 'BUREAU', 'GRANDE_SALLE') ELSE 'PETIT_BASSIN' END FROM planning_creneau_lieu_activite p JOIN lieu_activite la ON la.id = p.lieu_activite_id WHERE p.planning_creneau_id = c.id ORDER BY la.ordre LIMIT 1), 'PETIT_BASSIN')");
        $this->addSql("ALTER TABLE planning_creneau ALTER lieu DROP DEFAULT");

        $this->addSql('DROP TABLE planning_creneau_lieu_activite');
        $this->addSql('DROP TABLE lieu_activite');
    }
}
