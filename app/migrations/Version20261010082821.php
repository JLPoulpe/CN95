<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261010082821 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Inscriptions à la fosse et configuration des mails';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE invitation_fosse (id INT AUTO_INCREMENT NOT NULL, date_fosse DATE NOT NULL, statut VARCHAR(20) NOT NULL, dp_id INT NOT NULL, UNIQUE INDEX UNIQ_B0A36F6F669813C2 (date_fosse), INDEX IDX_B0A36F6F58AF9A2E (dp_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invitation_fosse_aptitude (invitation_fosse_id INT NOT NULL, aptitude_id INT NOT NULL, INDEX IDX_D3EC300C8870CCA7 (invitation_fosse_id), INDEX IDX_D3EC300CD4FDF611 (aptitude_id), PRIMARY KEY (invitation_fosse_id, aptitude_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mail_configuration (id INT AUTO_INCREMENT NOT NULL, plugin_code VARCHAR(50) NOT NULL, expediteur_nom VARCHAR(100) NOT NULL, expediteur_email VARCHAR(180) NOT NULL, config_chiffree LONGTEXT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reponse_fosse (id INT AUTO_INCREMENT NOT NULL, statut VARCHAR(20) NOT NULL, token VARCHAR(64) NOT NULL, repondue_at DATETIME DEFAULT NULL, invitation_id INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_3381802D5F37A13B (token), UNIQUE INDEX UNIQ_3381802DA35D7AF0A76ED395 (invitation_id, user_id), INDEX IDX_3381802DA35D7AF0 (invitation_id), INDEX IDX_3381802DA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE invitation_fosse ADD CONSTRAINT FK_B0A36F6F58AF9A2E FOREIGN KEY (dp_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE invitation_fosse_aptitude ADD CONSTRAINT FK_D3EC300C8870CCA7 FOREIGN KEY (invitation_fosse_id) REFERENCES invitation_fosse (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitation_fosse_aptitude ADD CONSTRAINT FK_D3EC300CD4FDF611 FOREIGN KEY (aptitude_id) REFERENCES aptitude (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reponse_fosse ADD CONSTRAINT FK_3381802DA35D7AF0 FOREIGN KEY (invitation_id) REFERENCES invitation_fosse (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE reponse_fosse ADD CONSTRAINT FK_3381802DA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE invitation_fosse DROP FOREIGN KEY FK_B0A36F6F58AF9A2E');
        $this->addSql('ALTER TABLE invitation_fosse_aptitude DROP FOREIGN KEY FK_D3EC300C8870CCA7');
        $this->addSql('ALTER TABLE invitation_fosse_aptitude DROP FOREIGN KEY FK_D3EC300CD4FDF611');
        $this->addSql('ALTER TABLE reponse_fosse DROP FOREIGN KEY FK_3381802DA35D7AF0');
        $this->addSql('ALTER TABLE reponse_fosse DROP FOREIGN KEY FK_3381802DA76ED395');
        $this->addSql('DROP TABLE invitation_fosse');
        $this->addSql('DROP TABLE invitation_fosse_aptitude');
        $this->addSql('DROP TABLE mail_configuration');
        $this->addSql('DROP TABLE reponse_fosse');
    }
}
