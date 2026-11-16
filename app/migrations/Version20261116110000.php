<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Rôle « Gestionnaire de salle » et permission PERM_GESTION_SALLES. */
final class Version20261116110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rôle GESTIONNAIRE_SALLE et permission PERM_GESTION_SALLES';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO `role` (code, libelle) SELECT 'GESTIONNAIRE_SALLE', 'Gestionnaire de salle' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM `role` WHERE code = 'GESTIONNAIRE_SALLE')");
        $this->addSql("INSERT INTO role_permission (role_id, permission) SELECT id, 'PERM_GESTION_SALLES' FROM `role` WHERE code = 'GESTIONNAIRE_SALLE'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permission WHERE permission = 'PERM_GESTION_SALLES'");
        $this->addSql("DELETE FROM `role` WHERE code = 'GESTIONNAIRE_SALLE'");
    }
}
