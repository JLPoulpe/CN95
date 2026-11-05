<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Moniteurs : aptitudes préparées dont ils ont la charge. */
final class Version20261104100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table user_aptitude_encadree (moniteur par groupe)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_aptitude_encadree (user_id INT NOT NULL, aptitude_id INT NOT NULL, INDEX IDX_A721828AA76ED395 (user_id), INDEX IDX_A721828AD4FDF611 (aptitude_id), PRIMARY KEY (user_id, aptitude_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_aptitude_encadree ADD CONSTRAINT FK_USER_APT_ENC_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_aptitude_encadree ADD CONSTRAINT FK_USER_APT_ENC_APT FOREIGN KEY (aptitude_id) REFERENCES aptitude (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_aptitude_encadree');
    }
}
