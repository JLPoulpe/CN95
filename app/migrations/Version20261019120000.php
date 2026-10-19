<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Communications officielles par mail : titre, contenu, auteur, état d'envoi. */
final class Version20261019120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Communications officielles : table communication';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE communication (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(150) NOT NULL, contenu LONGTEXT NOT NULL, created_at DATETIME NOT NULL, envoyee_le DATETIME DEFAULT NULL, nb_destinataires INT DEFAULT NULL, auteur_id INT DEFAULT NULL, INDEX IDX_F9AFB5EB60BB6FE6 (auteur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE communication ADD CONSTRAINT FK_F9AFB5EB60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES `user` (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE communication');
    }
}
