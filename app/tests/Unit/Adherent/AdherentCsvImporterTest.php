<?php

namespace App\Tests\Unit\Adherent;

use App\Adherent\AdherentCsvImporter;
use App\Entity\Aptitude;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\AptitudeRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdherentCsvImporterTest extends TestCase
{
    private const ENTETE = "nom;prenom;email;telephone;date_naissance;caci_expire_le;aptitude\n";

    /** @param list<User> $existants @param list<User> $persistes */
    private function importer(array $existants = [], array &$persistes = []): AdherentCsvImporter
    {
        $users = $this->createStub(UserRepository::class);
        $users->method('findAll')->willReturn($existants);
        $aptitudes = $this->createStub(AptitudeRepository::class);
        $aptitudes->method('findOneBy')->willReturnCallback(
            fn (array $c): ?Aptitude => in_array($c['code'], [...array_keys(AdherentCsvImporter::APTITUDE_PREPAREE), ...array_values(AdherentCsvImporter::APTITUDE_PREPAREE), 'N5'], true) ? (new Aptitude())->setCode($c['code']) : null,
        );
        $roles = $this->createStub(RoleRepository::class);
        $roles->method('findOneBy')->willReturn((new Role())->setCode(Role::ADHERENT));
        $hasher = $this->createStub(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hash');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function ($o) use (&$persistes) { $persistes[] = $o; });

        return new AdherentCsvImporter($em, $users, $aptitudes, $roles, $hasher);
    }

    private function fichier(string $contenu): string
    {
        $f = tempnam(sys_get_temp_dir(), 'adh');
        file_put_contents($f, $contenu);

        return $f;
    }

    public function testCreeLesComptesAvecActivationEnAttente(): void
    {
        $persistes = [];
        $f = $this->fichier(self::ENTETE."Delacroix;Émilie-Rose;ER@Example.org;0612345678;2002-08-29;2026-09-10;N1\n");
        $r = $this->importer([], $persistes)->import($f);
        unlink($f);

        $this->assertSame([], $r['errors']);
        $this->assertCount(1, $persistes);
        /** @var User $u */
        $u = $persistes[0];
        $this->assertSame('emilie-rose', $u->getUsername());
        $this->assertSame('er@example.org', $u->getEmail());
        $this->assertSame('2002-08-29', $u->getDateNaissance()->format('Y-m-d'));
        $this->assertSame('2026-09-10', $u->getCaciExpireLe()->format('Y-m-d'));
        $this->assertTrue($u->activationEnAttente());
        $this->assertTrue($u->hasRole(Role::ADHERENT));
        // Le fichier donne N1 (actuelle) : le compte garde N1 et reçoit PE40 (préparée)
        $this->assertSame('N1', $u->getAptitude()->getCode());
        $this->assertSame('PE40', $u->getAptitudePreparee()->getCode());
    }

    public function testAttribueLAptitudePrepareeSelonLaProgression(): void
    {
        $persistes = [];
        $lignes = '';
        foreach (['PN1', 'N1', 'PE40', 'N2', 'PE60', 'N3', 'N4', 'E1', 'E2', 'E3', 'MF1'] as $i => $actuelle) {
            $lignes .= sprintf("Nom%d;Prenom%d;p%d@example.org;;;;%s\n", $i, $i, $i, $actuelle);
        }
        $f = $this->fichier(self::ENTETE.$lignes);
        $r = $this->importer([], $persistes)->import($f);
        unlink($f);

        $this->assertSame([], $r['errors']);
        $actuelles = array_map(fn (User $u): string => $u->getAptitude()->getCode(), $persistes);
        $preparees = array_map(fn (User $u): string => $u->getAptitudePreparee()->getCode(), $persistes);
        $this->assertSame(['PN1', 'N1', 'PE40', 'N2', 'PE60', 'N3', 'N4', 'E1', 'E2', 'E3', 'MF1'], $actuelles);
        $this->assertSame(['N1', 'PE40', 'N2', 'PE60', 'N3', 'N4', 'N5', 'E2', 'E3', 'E4', 'MF2'], $preparees);
    }

    public function testAptitudeSansSuivanteLaisseLaPrepareeVide(): void
    {
        $persistes = [];
        $f = $this->fichier(self::ENTETE."Fin;Parcours;f@example.org;;;;N5\n");
        $r = $this->importer([], $persistes)->import($f);
        unlink($f);

        $this->assertSame([], $r['errors']);
        $this->assertSame('N5', $persistes[0]->getAptitude()->getCode());
        $this->assertNull($persistes[0]->getAptitudePreparee());
    }

    public function testPrenomPartageOuDejaPrisDonnePrenomPuisNom(): void
    {
        $existant = (new User())->setUsername('lucas')->setEmail('x@example.org');
        $persistes = [];
        $f = $this->fichier(self::ENTETE
            ."Breda;Cyril;a@example.org;;1977-01-28;;PN1\n"
            ."Claro;Cyril;b@example.org;;;;PN1\n"
            ."Delmas;Lucas;c@example.org;;;;N1\n"
            ."Seul;Nora;d@example.org;;;;N1\n");
        $r = $this->importer([$existant], $persistes)->import($f);
        unlink($f);

        $this->assertSame(['cyril.breda', 'cyril.claro', 'lucas.delmas', 'nora'], array_column($r['created'], 'username'));
    }

    public function testSimulationNePersistePas(): void
    {
        $persistes = [];
        $f = $this->fichier(self::ENTETE."Mevrel;Kilian;k@example.org;;;;N1\n");
        $r = $this->importer([], $persistes)->import($f, true);
        unlink($f);

        $this->assertCount(1, $r['created']);
        $this->assertSame([], $persistes);
    }

    public function testIgnoreLesEmailsExistantsEtSignaleLesErreurs(): void
    {
        $existant = (new User())->setUsername('kilian')->setEmail('K@example.org');
        $persistes = [];
        $f = $this->fichier(self::ENTETE
            ."Mevrel;Kilian;k@example.org;;;;N1\n"
            ."Un;Mauvais;pas-un-email;;;;N1\n"
            ."Deux;Date;d@example.org;;28/01/1977;;N1\n"
            ."Trois;Tel;t@example.org;06 12 34;;;N1\n"
            ."Quatre;Niveau;n@example.org;;;;Z9\n"
            ."Cinq;Dup;e@example.org;;;;N1\n"
            ."Six;Dup;E@example.org;;;;N1\n");
        $r = $this->importer([$existant], $persistes)->import($f);
        unlink($f);

        $this->assertCount(1, $r['ignored']);
        $this->assertCount(5, $r['errors']);
        $this->assertSame(['dup'], array_column($r['created'], 'username'));
    }

    public function testRefuseUnFichierSansToutesLesColonnes(): void
    {
        $f = $this->fichier("nom;prenom;email\nA;B;c@example.org\n");
        $r = $this->importer()->import($f);
        unlink($f);

        $this->assertStringContainsString('Colonnes manquantes', $r['errors'][0]);
    }
}
