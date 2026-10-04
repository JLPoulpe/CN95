<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004141149 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Planning (semaines, créneaux) et rôles multiples utilisateur';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE planning_creneau (id INT AUTO_INCREMENT NOT NULL, jour VARCHAR(10) NOT NULL, lieu VARCHAR(20) NOT NULL, lignes VARCHAR(30) DEFAULT NULL, activite VARCHAR(20) NOT NULL, libelle VARCHAR(100) DEFAULT NULL, semaine_id INT NOT NULL, INDEX IDX_240BD6B4122EEC90 (semaine_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planning_creneau_aptitude (planning_creneau_id INT NOT NULL, aptitude_id INT NOT NULL, INDEX IDX_7EB4EB38E4A628C9 (planning_creneau_id), INDEX IDX_7EB4EB38D4FDF611 (aptitude_id), PRIMARY KEY (planning_creneau_id, aptitude_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planning_semaine (id INT AUTO_INCREMENT NOT NULL, lundi DATE NOT NULL, fermee TINYINT NOT NULL, motif VARCHAR(150) DEFAULT NULL, dp_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_A620D601B1B7AC8A (lundi), INDEX IDX_A620D60158AF9A2E (dp_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `role` (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(30) NOT NULL, libelle VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_57698A6A77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE user_role (user_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_2DE8C6A3A76ED395 (user_id), INDEX IDX_2DE8C6A3D60322AC (role_id), PRIMARY KEY (user_id, role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE planning_creneau ADD CONSTRAINT FK_240BD6B4122EEC90 FOREIGN KEY (semaine_id) REFERENCES planning_semaine (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_creneau_aptitude ADD CONSTRAINT FK_7EB4EB38E4A628C9 FOREIGN KEY (planning_creneau_id) REFERENCES planning_creneau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_creneau_aptitude ADD CONSTRAINT FK_7EB4EB38D4FDF611 FOREIGN KEY (aptitude_id) REFERENCES aptitude (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE planning_semaine ADD CONSTRAINT FK_A620D60158AF9A2E FOREIGN KEY (dp_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3A76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_role ADD CONSTRAINT FK_2DE8C6A3D60322AC FOREIGN KEY (role_id) REFERENCES `role` (id) ON DELETE CASCADE');
        $this->addSql("INSERT INTO `role` (code, libelle) VALUES ('ADHERENT', 'Adhérent'), ('MONITEUR', 'Moniteur'), ('BUREAU', 'Membre du bureau'), ('ADMIN', 'Administrateur')");
        // Conversion des anciens rôles : tout le monde est adhérent, les ROLE_ADMIN deviennent administrateurs
        $this->addSql("INSERT INTO user_role (user_id, role_id) SELECT u.id, r.id FROM `user` u JOIN `role` r ON r.code = 'ADHERENT'");
        $this->addSql("INSERT INTO user_role (user_id, role_id) SELECT u.id, r.id FROM `user` u JOIN `role` r ON r.code = 'ADMIN' WHERE u.roles LIKE '%ROLE_ADMIN%'");
        $this->addSql('ALTER TABLE `user` DROP roles');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_creneau DROP FOREIGN KEY FK_240BD6B4122EEC90');
        $this->addSql('ALTER TABLE planning_creneau_aptitude DROP FOREIGN KEY FK_7EB4EB38E4A628C9');
        $this->addSql('ALTER TABLE planning_creneau_aptitude DROP FOREIGN KEY FK_7EB4EB38D4FDF611');
        $this->addSql('ALTER TABLE planning_semaine DROP FOREIGN KEY FK_A620D60158AF9A2E');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3A76ED395');
        $this->addSql('ALTER TABLE user_role DROP FOREIGN KEY FK_2DE8C6A3D60322AC');
        $this->addSql('ALTER TABLE `user` ADD roles JSON NOT NULL');
        $this->addSql("UPDATE `user` SET roles = '[]'");
        $this->addSql("UPDATE `user` u JOIN user_role ur ON ur.user_id = u.id JOIN `role` r ON r.id = ur.role_id AND r.code = 'ADMIN' SET u.roles = '[\"ROLE_ADMIN\"]'");
        $this->addSql('DROP TABLE planning_creneau');
        $this->addSql('DROP TABLE planning_creneau_aptitude');
        $this->addSql('DROP TABLE planning_semaine');
        $this->addSql('DROP TABLE `role`');
        $this->addSql('DROP TABLE user_role');
    }
}
