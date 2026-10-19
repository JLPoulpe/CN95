<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Fin des modèles de mails en base : les contenus sont désormais des fichiers Twig (templates/mail/).
 *
 * Le texte fixe de l'invitation par défaut est conservé : il devient le texte par défaut de l'activité FOSSE,
 * pré-rempli dans le commentaire des nouvelles invitations. Les paragraphes qui utilisaient des variables
 * ({{date}}, {{activite}}) sont retirés : la phrase d'introduction est maintenant écrite par le mail lui-même.
 *
 * Les autres modèles (annulation, désistement, activation) sont perdus : leurs textes d'origine sont repris
 * dans templates/mail/. Aucun retour arrière possible.
 */
final class Version20261019110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Supprime mail_template ; texte par défaut des activités externes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activite_externe ADD texte_par_defaut LONGTEXT DEFAULT NULL');

        $contenu = $this->connection->fetchOne(
            "SELECT contenu FROM mail_template WHERE type = 'INVITATION' AND par_defaut = 1 LIMIT 1",
        );
        if (is_string($contenu) && '' !== trim($contenu)) {
            $texte = preg_replace('#<p>(?:(?!</p>).)*\{\{(?:(?!</p>).)*</p>\s*#s', '', $contenu);
            $texte = trim((string) $texte);
            if ('' !== $texte) {
                $this->addSql("UPDATE activite_externe SET texte_par_defaut = ? WHERE code = 'FOSSE'", [$texte]);
            }
        }

        $this->addSql('ALTER TABLE invitation_fosse DROP FOREIGN KEY `FK_B0A36F6FB1057265`');
        $this->addSql('DROP INDEX IDX_B0A36F6FB1057265 ON invitation_fosse');
        $this->addSql('ALTER TABLE invitation_fosse DROP mail_template_id');
        $this->addSql('DROP TABLE mail_template');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les modèles de mails supprimés ne peuvent pas être restaurés ; restaurer une sauvegarde.');
    }
}
