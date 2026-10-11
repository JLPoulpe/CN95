<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261010125154 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Table activite_externe (fosse et futures activités) liée aux invitations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE activite_externe (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, libelle VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_784CDAEB77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql("INSERT INTO activite_externe (code, libelle) VALUES ('FOSSE', 'Fosse')");
        $this->addSql('ALTER TABLE invitation_fosse ADD activite_id INT NOT NULL');
        $this->addSql('ALTER TABLE invitation_fosse ADD CONSTRAINT FK_B0A36F6F9B0F88B1 FOREIGN KEY (activite_id) REFERENCES activite_externe (id)');
        $this->addSql('CREATE INDEX IDX_B0A36F6F9B0F88B1 ON invitation_fosse (activite_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE activite_externe');
        $this->addSql('ALTER TABLE invitation_fosse DROP FOREIGN KEY FK_B0A36F6F9B0F88B1');
        $this->addSql('DROP INDEX IDX_B0A36F6F9B0F88B1 ON invitation_fosse');
        $this->addSql('ALTER TABLE invitation_fosse DROP activite_id');
    }
}
