<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Aligne les noms d'index générés manuellement dans les migrations précédentes
 * sur la convention de nommage de Doctrine (différence purement cosmétique).
 */
final class Version20261010141500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Alignement des noms d\'index sur la convention Doctrine';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite RENAME INDEX uniq_ac74095a77153098 TO UNIQ_B875551577153098');
        $this->addSql('ALTER TABLE activite_externe CHANGE necessite_responsable necessite_responsable TINYINT NOT NULL');
        $this->addSql('ALTER TABLE ligne_eau RENAME INDEX uniq_3f3ce2e0_lieu_numero TO UNIQ_3F3CF0982F577D59F55AE19E');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau RENAME INDEX idx_c4e1b2a12514723f TO IDX_F43843ACE4A628C9');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau RENAME INDEX idx_c4e1b2a1f5a08fc7 TO IDX_F43843ACAE92B17D');
        $this->addSql('ALTER TABLE planning_creneau_activite RENAME INDEX idx_7e2a8f6b2514723f TO IDX_DD75EDA5E4A628C9');
        $this->addSql('ALTER TABLE planning_creneau_activite RENAME INDEX idx_7e2a8f6b2cb405b4 TO IDX_DD75EDA59B0F88B1');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE activite RENAME INDEX uniq_b875551577153098 TO UNIQ_AC74095A77153098');
        $this->addSql('ALTER TABLE activite_externe CHANGE necessite_responsable necessite_responsable TINYINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE ligne_eau RENAME INDEX uniq_3f3cf0982f577d59f55ae19e TO UNIQ_3F3CE2E0_LIEU_NUMERO');
        $this->addSql('ALTER TABLE planning_creneau_activite RENAME INDEX idx_dd75eda5e4a628c9 TO IDX_7E2A8F6B2514723F');
        $this->addSql('ALTER TABLE planning_creneau_activite RENAME INDEX idx_dd75eda59b0f88b1 TO IDX_7E2A8F6B2CB405B4');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau RENAME INDEX idx_f43843ace4a628c9 TO IDX_C4E1B2A12514723F');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau RENAME INDEX idx_f43843acae92b17d TO IDX_C4E1B2A1F5A08FC7');
    }
}
