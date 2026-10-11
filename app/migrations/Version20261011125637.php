<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261011125637 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE activite (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, libelle VARCHAR(100) DEFAULT NULL, ordre INT NOT NULL, UNIQUE INDEX UNIQ_B875551577153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE activite_externe (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, libelle VARCHAR(100) NOT NULL, necessite_responsable TINYINT NOT NULL, UNIQUE INDEX UNIQ_784CDAEB77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invitation_fosse (id INT AUTO_INCREMENT NOT NULL, date_fosse DATE NOT NULL, statut VARCHAR(20) NOT NULL, commentaire LONGTEXT DEFAULT NULL, dp_id INT DEFAULT NULL, activite_id INT NOT NULL, UNIQUE INDEX uniq_invitation_fosse_date_activite (date_fosse, activite_id), INDEX IDX_B0A36F6F58AF9A2E (dp_id), INDEX IDX_B0A36F6F9B0F88B1 (activite_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invitation_fosse_aptitude (invitation_fosse_id INT NOT NULL, aptitude_id INT NOT NULL, INDEX IDX_D3EC300C8870CCA7 (invitation_fosse_id), INDEX IDX_D3EC300CD4FDF611 (aptitude_id), PRIMARY KEY (invitation_fosse_id, aptitude_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ligne_eau (id INT AUTO_INCREMENT NOT NULL, lieu VARCHAR(20) NOT NULL, numero INT NOT NULL, UNIQUE INDEX UNIQ_3F3CF0982F577D59F55AE19E (lieu, numero), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mail_configuration (id INT AUTO_INCREMENT NOT NULL, plugin_code VARCHAR(50) NOT NULL, expediteur_nom VARCHAR(100) NOT NULL, expediteur_email VARCHAR(180) NOT NULL, config_chiffree LONGTEXT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planning_creneau_ligne_eau (planning_creneau_id INT NOT NULL, ligne_eau_id INT NOT NULL, INDEX IDX_F43843ACE4A628C9 (planning_creneau_id), INDEX IDX_F43843ACAE92B17D (ligne_eau_id), PRIMARY KEY (planning_creneau_id, ligne_eau_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planning_creneau_activite (planning_creneau_id INT NOT NULL, activite_id INT NOT NULL, INDEX IDX_DD75EDA5E4A628C9 (planning_creneau_id), INDEX IDX_DD75EDA59B0F88B1 (activite_id), PRIMARY KEY (planning_creneau_id, activite_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reponse_fosse (id INT AUTO_INCREMENT NOT NULL, statut VARCHAR(20) NOT NULL, token VARCHAR(64) NOT NULL, repondue_at DATETIME DEFAULT NULL, invitation_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_3381802D5F37A13B (token), UNIQUE INDEX UNIQ_3381802DA35D7AF0A76ED395 (invitation_id, user_id), INDEX IDX_3381802DA35D7AF0 (invitation_id), INDEX IDX_3381802DA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE invitation_fosse ADD CONSTRAINT FK_B0A36F6F58AF9A2E FOREIGN KEY (dp_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE invitation_fosse ADD CONSTRAINT FK_B0A36F6F9B0F88B1 FOREIGN KEY (activite_id) REFERENCES activite_externe (id)');
        $this->addSql('ALTER TABLE invitation_fosse_aptitude ADD CONSTRAINT FK_D3EC300C8870CCA7 FOREIGN KEY (invitation_fosse_id) REFERENCES invitation_fosse (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitation_fosse_aptitude ADD CONSTRAINT FK_D3EC300CD4FDF611 FOREIGN KEY (aptitude_id) REFERENCES aptitude (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau ADD CONSTRAINT FK_F43843ACE4A628C9 FOREIGN KEY (planning_creneau_id) REFERENCES planning_creneau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau ADD CONSTRAINT FK_F43843ACAE92B17D FOREIGN KEY (ligne_eau_id) REFERENCES ligne_eau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_creneau_activite ADD CONSTRAINT FK_DD75EDA5E4A628C9 FOREIGN KEY (planning_creneau_id) REFERENCES planning_creneau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_creneau_activite ADD CONSTRAINT FK_DD75EDA59B0F88B1 FOREIGN KEY (activite_id) REFERENCES activite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reponse_fosse ADD CONSTRAINT FK_3381802DA35D7AF0 FOREIGN KEY (invitation_id) REFERENCES invitation_fosse (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reponse_fosse ADD CONSTRAINT FK_3381802DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE planning_creneau DROP lignes, DROP activites');
        $this->addSql('ALTER TABLE user ADD activation_token_hash VARCHAR(64) DEFAULT NULL, ADD activation_expire_le DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invitation_fosse DROP FOREIGN KEY FK_B0A36F6F58AF9A2E');
        $this->addSql('ALTER TABLE invitation_fosse DROP FOREIGN KEY FK_B0A36F6F9B0F88B1');
        $this->addSql('ALTER TABLE invitation_fosse_aptitude DROP FOREIGN KEY FK_D3EC300C8870CCA7');
        $this->addSql('ALTER TABLE invitation_fosse_aptitude DROP FOREIGN KEY FK_D3EC300CD4FDF611');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau DROP FOREIGN KEY FK_F43843ACE4A628C9');
        $this->addSql('ALTER TABLE planning_creneau_ligne_eau DROP FOREIGN KEY FK_F43843ACAE92B17D');
        $this->addSql('ALTER TABLE planning_creneau_activite DROP FOREIGN KEY FK_DD75EDA5E4A628C9');
        $this->addSql('ALTER TABLE planning_creneau_activite DROP FOREIGN KEY FK_DD75EDA59B0F88B1');
        $this->addSql('ALTER TABLE reponse_fosse DROP FOREIGN KEY FK_3381802DA35D7AF0');
        $this->addSql('ALTER TABLE reponse_fosse DROP FOREIGN KEY FK_3381802DA76ED395');
        $this->addSql('DROP TABLE activite');
        $this->addSql('DROP TABLE activite_externe');
        $this->addSql('DROP TABLE invitation_fosse');
        $this->addSql('DROP TABLE invitation_fosse_aptitude');
        $this->addSql('DROP TABLE ligne_eau');
        $this->addSql('DROP TABLE mail_configuration');
        $this->addSql('DROP TABLE planning_creneau_ligne_eau');
        $this->addSql('DROP TABLE planning_creneau_activite');
        $this->addSql('DROP TABLE reponse_fosse');
        $this->addSql('ALTER TABLE planning_creneau ADD lignes VARCHAR(30) DEFAULT NULL, ADD activites JSON NOT NULL');
        $this->addSql('ALTER TABLE `user` DROP activation_token_hash, DROP activation_expire_le');
    }
}
