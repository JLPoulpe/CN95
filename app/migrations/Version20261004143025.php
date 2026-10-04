<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261004143025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Un créneau peut avoir plusieurs activités';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_creneau ADD activites JSON NOT NULL');
        $this->addSql('UPDATE planning_creneau SET activites = JSON_ARRAY(activite)');
        $this->addSql('ALTER TABLE planning_creneau DROP activite');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_creneau ADD activite VARCHAR(20) NOT NULL');
        $this->addSql("UPDATE planning_creneau SET activite = COALESCE(JSON_VALUE(activites, '$[0]'), 'LIBRE')");
        $this->addSql('ALTER TABLE planning_creneau DROP activites');
    }
}
