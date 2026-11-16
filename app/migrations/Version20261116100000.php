<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ordre manuel de promotion en liste d\'attente de la fosse';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reponse_fosse ADD ordre INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reponse_fosse DROP ordre');
    }
}
