<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Information RGPD : table des demandes (accusé de lecture, choix sur les communications) et dates de création
 * des comptes et des invitations, utilisées par la purge annuelle du 31/08 (app:rgpd:purger).
 *
 * Données existantes : created_at prend la date de la migration. Tous les comptes et invitations existants seront
 * donc purgés au premier 31/08 qui suit.
 */
final class Version20261015100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'RGPD : demandes d\'information et dates de création (user, invitation_fosse)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE demande_rgpd (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, token_hash VARCHAR(64) NOT NULL, version VARCHAR(20) NOT NULL, accord TINYINT DEFAULT NULL, envoyee_le DATETIME NOT NULL, repondue_le DATETIME DEFAULT NULL, user_id INT NOT NULL, UNIQUE INDEX UNIQ_AFAFC4CCB3BC57DA (token_hash), INDEX IDX_AFAFC4CCA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE demande_rgpd ADD CONSTRAINT FK_AFAFC4CCA76ED395 FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE invitation_fosse ADD created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE demande_rgpd DROP FOREIGN KEY FK_AFAFC4CCA76ED395');
        $this->addSql('DROP TABLE demande_rgpd');
        $this->addSql('ALTER TABLE invitation_fosse DROP created_at');
        $this->addSql('ALTER TABLE user DROP created_at');
    }
}
