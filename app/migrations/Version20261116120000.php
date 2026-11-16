<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Absences de moniteurs sur les créneaux du planning';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE absence_moniteur (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, creneau_id INT NOT NULL, moniteur_id INT NOT NULL, declare_par_id INT DEFAULT NULL, INDEX IDX_5046038D7D0729A9 (creneau_id), INDEX IDX_5046038DA234A5D3 (moniteur_id), INDEX IDX_5046038D8B43E2A (declare_par_id), UNIQUE INDEX UNIQ_5046038D7D0729A9A234A5D3 (creneau_id, moniteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE absence_moniteur ADD CONSTRAINT FK_ABSMON_CRENEAU FOREIGN KEY (creneau_id) REFERENCES planning_creneau (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE absence_moniteur ADD CONSTRAINT FK_ABSMON_MONITEUR FOREIGN KEY (moniteur_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE absence_moniteur ADD CONSTRAINT FK_ABSMON_DECLARE FOREIGN KEY (declare_par_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE absence_moniteur');
    }
}
