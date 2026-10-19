<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Permissions modifiables depuis l'administration : table role_permission, remplie avec la configuration par défaut
 * (copie de Permission::DEFAUTS à la date de la migration).
 */
final class Version20261019100000 extends AbstractMigration
{
    private const DEFAUTS = [
        'PERM_INVITATIONS' => ['BUREAU'],
        'PERM_COMMUNICATION' => ['BUREAU'],
        'PERM_ACTIVITES_EXTERNES' => ['BUREAU', 'MONITEUR'],
        'PERM_CONFIG_ACTIVITES' => ['BUREAU'],
    ];

    public function getDescription(): string
    {
        return 'Permissions : table role_permission et configuration par défaut';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE role_permission (id INT AUTO_INCREMENT NOT NULL, permission VARCHAR(50) NOT NULL, role_id INT NOT NULL, UNIQUE INDEX uniq_role_permission (role_id, permission), INDEX IDX_6F7DF886D60322AC (role_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE role_permission ADD CONSTRAINT FK_6F7DF886D60322AC FOREIGN KEY (role_id) REFERENCES `role` (id) ON DELETE CASCADE');

        foreach (self::DEFAUTS as $permission => $codes) {
            foreach ($codes as $code) {
                $this->addSql('INSERT INTO role_permission (role_id, permission) SELECT id, ? FROM `role` WHERE code = ?', [$permission, $code]);
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE role_permission');
    }
}
