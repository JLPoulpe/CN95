<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Modèles de mails en base, invitations rattachées à un modèle et à des rôles.
 *
 * Données existantes : chaque invitation reçoit le modèle Invitation par défaut et les rôles MONITEUR et BUREAU
 * (hypothèse de départ, à ajuster depuis l'administration si besoin).
 */
final class Version20261014120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Modèles de mails en base, rôles et modèle de mail par invitation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mail_template (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, type VARCHAR(30) NOT NULL, contenu LONGTEXT NOT NULL, par_defaut TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_4AB7DECB6C6E55B5 (nom), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE invitation_fosse_role (invitation_fosse_id INT NOT NULL, role_id INT NOT NULL, INDEX IDX_4D90B0398870CCA7 (invitation_fosse_id), INDEX IDX_4D90B039D60322AC (role_id), PRIMARY KEY (invitation_fosse_id, role_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE invitation_fosse_role ADD CONSTRAINT FK_4D90B0398870CCA7 FOREIGN KEY (invitation_fosse_id) REFERENCES invitation_fosse (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE invitation_fosse_role ADD CONSTRAINT FK_4D90B039D60322AC FOREIGN KEY (role_id) REFERENCES `role` (id) ON DELETE CASCADE');

        // Contenus repris des anciens fichiers templates/mail/** (voir App\Mail\MailTemplateDefaults)
        $this->addSql('INSERT INTO mail_template (nom, type, contenu, par_defaut, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())', [
            'Invitation par défaut',
            'INVITATION',
            <<<'HTML'
<p>Vous êtes invité(e) à l'activité <strong>{{activite}}</strong> du <strong>{{date}}</strong>.</p>
<p>Le rendez-vous est fixé à 19h30 sur place pour être à 20h00 dans l’eau.</p>
<p>Le nombre de participants est toujours limité à 30, alors ne tardez pas à vous inscrire.</p>
<p>Les mineurs devront présenter une autorisation parentale.</p>
<p>Les documents seront vérifiés avant la constitution des palanquées</p>
<p><strong>⚠ Pas de documents = pas de plongée ⚠</strong></p>
<p>Si vous souhaitez faire du covoiturage faites-le nous savoir (départ 19h00 de la piscine).</p>
<p>À respecter avant d’accéder à la fosse :</p>
<ul>
    <li>Déchaussage obligatoire avant l'entrée dans les vestiaires.</li>
    <li>Passage cabine obligatoire pour se changer.</li>
    <li>Douche savonnée obligatoire pour tous avant l'accès au bassin.</li>
</ul>
<p>Nous comptons sur vous afin de respecter les conditions d'accès et d'hygiène données par AQUA 92 en faisant preuve d'organisation et de discipline.</p>
<p>A vos palmes, prêts, plongez ! Pour le Comité Directeur, Olivier, responsable de la communication.</p>
<p>Adresse : 119 Boulevard Charles de Gaulle - 92390 Villeneuve-la-Garenne<br>
<a href="https://aqua92.ucpa.com/">https://aqua92.ucpa.com/</a></p>
HTML,
            1,
        ]);
        $this->addSql('INSERT INTO mail_template (nom, type, contenu, par_defaut, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())', [
            'Annulation',
            'ANNULATION',
            <<<'HTML'
<p>L'activité <strong>{{activite}}</strong> du <strong>{{date}}</strong> est annulée.</p>
HTML,
            1,
        ]);
        $this->addSql('INSERT INTO mail_template (nom, type, contenu, par_defaut, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())', [
            'Désistement (responsable)',
            'DESISTEMENT_RESPONSABLE',
            <<<'HTML'
<p><strong>{{membre}}</strong>, inscrit(e) à l'activité <strong>{{activite}}</strong> du <strong>{{date}}</strong>, ne pourra finalement pas participer.</p>
HTML,
            1,
        ]);
        $this->addSql('INSERT INTO mail_template (nom, type, contenu, par_defaut, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())', [
            'Activation de compte',
            'ACTIVATION_COMPTE',
            <<<'HTML'
<p>Ce lien est valable 7 jours. Passé ce délai, demandez à un administrateur de vous en renvoyer un.</p>
HTML,
            1,
        ]);

        $this->addSql('ALTER TABLE invitation_fosse ADD mail_template_id INT DEFAULT NULL');
        $this->addSql('UPDATE invitation_fosse SET mail_template_id = (SELECT id FROM mail_template WHERE type = \'INVITATION\' AND par_defaut = 1 LIMIT 1)');
        $this->addSql('ALTER TABLE invitation_fosse MODIFY mail_template_id INT NOT NULL');
        $this->addSql('ALTER TABLE invitation_fosse ADD CONSTRAINT FK_B0A36F6FB1057265 FOREIGN KEY (mail_template_id) REFERENCES mail_template (id)');
        $this->addSql('CREATE INDEX IDX_B0A36F6FB1057265 ON invitation_fosse (mail_template_id)');

        $this->addSql('INSERT INTO invitation_fosse_role (invitation_fosse_id, role_id) SELECT i.id, r.id FROM invitation_fosse i CROSS JOIN `role` r WHERE r.code IN (\'MONITEUR\', \'BUREAU\')');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invitation_fosse DROP FOREIGN KEY FK_B0A36F6FB1057265');
        $this->addSql('DROP INDEX IDX_B0A36F6FB1057265 ON invitation_fosse');
        $this->addSql('ALTER TABLE invitation_fosse DROP mail_template_id');
        $this->addSql('ALTER TABLE invitation_fosse_role DROP FOREIGN KEY FK_4D90B0398870CCA7');
        $this->addSql('ALTER TABLE invitation_fosse_role DROP FOREIGN KEY FK_4D90B039D60322AC');
        $this->addSql('DROP TABLE invitation_fosse_role');
        $this->addSql('DROP TABLE mail_template');
    }
}
