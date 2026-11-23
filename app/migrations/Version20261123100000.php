<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** « Gestionnaire de salle » devient « Gestionnaire de lieux d'activité » (rôle et permission renommés). */
final class Version20261123100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rôle GESTIONNAIRE_SALLE → GESTIONNAIRE_LIEUX et permission PERM_GESTION_SALLES → PERM_GESTION_LIEUX';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE `role` SET code = 'GESTIONNAIRE_LIEUX', libelle = 'Gestionnaire de lieux d''activité' WHERE code = 'GESTIONNAIRE_SALLE'");
        $this->addSql("UPDATE role_permission SET permission = 'PERM_GESTION_LIEUX' WHERE permission = 'PERM_GESTION_SALLES'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE role_permission SET permission = 'PERM_GESTION_SALLES' WHERE permission = 'PERM_GESTION_LIEUX'");
        $this->addSql("UPDATE `role` SET code = 'GESTIONNAIRE_SALLE', libelle = 'Gestionnaire de salle' WHERE code = 'GESTIONNAIRE_LIEUX'");
    }
}
