<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute un champ commentaire (texte libre) sur les invitations.
 */
final class Version20261010150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout du champ commentaire sur invitation_fosse';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_fosse ADD commentaire LONGTEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_fosse DROP commentaire');
    }
}
