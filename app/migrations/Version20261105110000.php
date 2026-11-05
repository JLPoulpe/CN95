<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Utilisateur : aptitude nullable au niveau base (compte admin sans aptitude)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` CHANGE aptitude_id aptitude_id INT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE `user` CHANGE aptitude_id aptitude_id INT NOT NULL');
    }
}
