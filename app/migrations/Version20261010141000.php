<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Généralise le workflow d'invitation à n'importe quelle activité externe :
 * - activite_externe.necessite_responsable (contrôle si un DP/responsable est demandé) ;
 * - invitation_fosse.dp_id devient optionnel ;
 * - l'unicité de la date d'invitation devient composite (date, activité), pour permettre
 *   plusieurs activités différentes le même jour.
 */
final class Version20261010141000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Généralisation du workflow invitation : necessite_responsable, dp optionnel, unicité (date, activite)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activite_externe ADD necessite_responsable TINYINT(1) NOT NULL DEFAULT 1');
        $this->addSql('ALTER TABLE invitation_fosse MODIFY dp_id INT DEFAULT NULL');
        $this->addSql('DROP INDEX UNIQ_B0A36F6F669813C2 ON invitation_fosse');
        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_fosse_date_activite ON invitation_fosse (date_fosse, activite_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_invitation_fosse_date_activite ON invitation_fosse');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B0A36F6F669813C2 ON invitation_fosse (date_fosse)');
        $this->addSql('ALTER TABLE invitation_fosse MODIFY dp_id INT NOT NULL');
        $this->addSql('ALTER TABLE activite_externe DROP necessite_responsable');
    }
}
