<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Paramètres de plongée d'une palanquée : profondeur max., DTR, durée et type. */
final class Version20261123120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Palanquée : profondeur max., DTR, durée et type';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE palanquee ADD profondeur_max SMALLINT DEFAULT NULL, ADD dtr SMALLINT DEFAULT NULL, ADD duree SMALLINT DEFAULT NULL, ADD type VARCHAR(20) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE palanquee DROP profondeur_max, DROP dtr, DROP duree, DROP type');
    }
}
