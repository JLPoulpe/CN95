<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** DP distinct le vendredi (planning_semaine.dp_vendredi_id). Les semaines existantes gardent le DP du lundi pour le vendredi. */
final class Version20261018100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Planning : DP du vendredi distinct de celui du lundi';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_semaine ADD dp_vendredi_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE planning_semaine ADD CONSTRAINT FK_A620D601C1DA4932 FOREIGN KEY (dp_vendredi_id) REFERENCES `user` (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A620D601C1DA4932 ON planning_semaine (dp_vendredi_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planning_semaine DROP FOREIGN KEY FK_A620D601C1DA4932');
        $this->addSql('DROP INDEX IDX_A620D601C1DA4932 ON planning_semaine');
        $this->addSql('ALTER TABLE planning_semaine DROP dp_vendredi_id');
    }
}
