<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Utilisateur : date de naissance et date d\'expiration du CACI';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` ADD date_naissance DATE DEFAULT NULL, ADD caci_expire_le DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` DROP date_naissance, DROP caci_expire_le');
    }
}
