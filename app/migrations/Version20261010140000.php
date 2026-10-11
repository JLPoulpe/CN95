<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Convertit l'enum PHP Activite en table gérable (admin), et le champ JSON
 * planning_creneau.activites en relation ManyToMany vers cette nouvelle table.
 */
final class Version20261010140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Table 'activite' (remplace l'enum PHP) + relation ManyToMany avec planning_creneau";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activite (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, libelle VARCHAR(100) DEFAULT NULL, ordre INT NOT NULL, UNIQUE INDEX UNIQ_AC74095A77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');

        $this->addSql("INSERT INTO activite (code, libelle, ordre) VALUES
            ('APNEE', 'Apnée', 1),
            ('PMT', 'PMT', 2),
            ('BLOC', 'Bloc', 3),
            ('NATATION', 'Natation', 4),
            ('LIBRE', 'Libre', 5),
            ('CODIR', 'CODIR', 6),
            ('COURS', 'Cours', 7)");

        $this->addSql('CREATE TABLE planning_creneau_activite (planning_creneau_id INT NOT NULL, activite_id INT NOT NULL, INDEX IDX_7E2A8F6B2514723F (planning_creneau_id), INDEX IDX_7E2A8F6B2CB405B4 (activite_id), PRIMARY KEY (planning_creneau_id, activite_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE planning_creneau_activite ADD CONSTRAINT FK_7E2A8F6B2514723F FOREIGN KEY (planning_creneau_id) REFERENCES planning_creneau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_creneau_activite ADD CONSTRAINT FK_7E2A8F6B2CB405B4 FOREIGN KEY (activite_id) REFERENCES activite (id) ON DELETE CASCADE');

        foreach (['APNEE', 'PMT', 'BLOC', 'NATATION', 'LIBRE', 'CODIR', 'COURS'] as $code) {
            $this->addSql(
                'INSERT INTO planning_creneau_activite (planning_creneau_id, activite_id) '
                ."SELECT pc.id, a.id FROM planning_creneau pc JOIN activite a ON a.code = '".$code."' "
                .'WHERE JSON_CONTAINS(pc.activites, \'"'.$code.'"\')'
            );
        }

        $this->addSql('ALTER TABLE planning_creneau DROP activites');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_creneau ADD activites JSON NOT NULL');
        $this->addSql("UPDATE planning_creneau pc SET activites = (
            SELECT JSON_ARRAYAGG(a.code) FROM planning_creneau_activite pca
            JOIN activite a ON a.id = pca.activite_id
            WHERE pca.planning_creneau_id = pc.id
        )");
        $this->addSql("UPDATE planning_creneau SET activites = JSON_ARRAY('LIBRE') WHERE activites IS NULL");

        $this->addSql('ALTER TABLE planning_creneau_activite DROP FOREIGN KEY FK_7E2A8F6B2514723F');
        $this->addSql('ALTER TABLE planning_creneau_activite DROP FOREIGN KEY FK_7E2A8F6B2CB405B4');
        $this->addSql('DROP TABLE planning_creneau_activite');
        $this->addSql('DROP TABLE activite');
    }
}
