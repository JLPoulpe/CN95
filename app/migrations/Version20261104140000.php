<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fiche de sécurité : fiche, palanquées et membres';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fiche_securite (id INT AUTO_INCREMENT NOT NULL, invitation_id INT NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_B784B31FA35D7AF0 (invitation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE palanquee (id INT AUTO_INCREMENT NOT NULL, fiche_id INT NOT NULL, numero INT NOT NULL, INDEX IDX_7BC236BDF522508 (fiche_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('CREATE TABLE palanquee_membre (id INT AUTO_INCREMENT NOT NULL, palanquee_id INT NOT NULL, user_id INT NOT NULL, position INT NOT NULL, INDEX IDX_4FEEAE815EBD6930 (palanquee_id), INDEX IDX_4FEEAE81A76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci`');
        $this->addSql('ALTER TABLE fiche_securite ADD CONSTRAINT FK_FICHE_SECURITE_INVITATION FOREIGN KEY (invitation_id) REFERENCES invitation_fosse (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE palanquee ADD CONSTRAINT FK_PALANQUEE_FICHE FOREIGN KEY (fiche_id) REFERENCES fiche_securite (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE palanquee_membre ADD CONSTRAINT FK_PALANQUEE_MEMBRE_PALANQUEE FOREIGN KEY (palanquee_id) REFERENCES palanquee (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE palanquee_membre ADD CONSTRAINT FK_PALANQUEE_MEMBRE_USER FOREIGN KEY (user_id) REFERENCES `user` (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE palanquee_membre');
        $this->addSql('DROP TABLE palanquee');
        $this->addSql('DROP TABLE fiche_securite');
    }
}
