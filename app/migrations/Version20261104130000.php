<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Invitation fosse : limite d\'inscrits et nombre de places en liste d\'attente';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_fosse ADD limite_inscrits INT DEFAULT NULL, ADD limite_attente INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_fosse DROP limite_inscrits, DROP limite_attente');
    }
}
