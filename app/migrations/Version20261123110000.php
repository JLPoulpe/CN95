<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Moniteurs extérieurs (sans compte) dans les palanquées : user_id devient facultatif, exterieur porte le numéro de l'étiquette. */
final class Version20261123110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Palanquée : moniteurs extérieurs (palanquee_membre.exterieur, user_id nullable)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE palanquee_membre ADD exterieur SMALLINT DEFAULT NULL, CHANGE user_id user_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM palanquee_membre WHERE user_id IS NULL');
        $this->addSql('ALTER TABLE palanquee_membre DROP exterieur, CHANGE user_id user_id INT NOT NULL');
    }
}
