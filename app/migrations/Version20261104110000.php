<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Permission « Planning de ses groupes », accordée par défaut au rôle MONITEUR. */
final class Version20261104110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permission PERM_PLANNING_GROUPES (rôle MONITEUR par défaut)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("INSERT INTO role_permission (role_id, permission) SELECT id, 'PERM_PLANNING_GROUPES' FROM `role` WHERE code = 'MONITEUR'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM role_permission WHERE permission = 'PERM_PLANNING_GROUPES'");
    }
}
