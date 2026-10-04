<?php

namespace App\Command;

use App\Entity\Aptitude;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\AptitudeRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Crée les aptitudes, les rôles et, si demandé, un compte administrateur')]
class SeedCommand extends Command
{
    private const APTITUDES = [
        'PN1' => 'Plongeur Niveau 1',
        'N1' => 'Niveau 1',
        'PE40' => 'Plongeur Encadré 40 m',
        'N2' => 'Niveau 2',
        'PE60' => 'Plongeur Encadré 60 m',
        'N3' => 'Niveau 3',
        'MF1' => 'Moniteur Fédéral 1er degré',
    ];

    private const ROLES = [
        Role::ADHERENT => 'Adhérent',
        Role::MONITEUR => 'Moniteur',
        Role::BUREAU => 'Membre du bureau',
        Role::ADMIN => 'Administrateur',
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private AptitudeRepository $aptitudes,
        private RoleRepository $roles,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('admin-username', null, InputOption::VALUE_REQUIRED, 'Identifiant de l\'administrateur à créer')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Mot de passe de l\'administrateur (ou variable ADMIN_PASSWORD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $ordre = 0;
        foreach (self::APTITUDES as $code => $libelle) {
            ++$ordre;
            $apt = $this->aptitudes->findOneBy(['code' => $code]) ?? (new Aptitude())->setCode($code);
            $apt->setLibelle($libelle)->setOrdre($ordre);
            $this->em->persist($apt);
        }
        $this->em->flush();
        $io->success(count(self::APTITUDES).' aptitudes synchronisées.');

        foreach (self::ROLES as $code => $libelle) {
            $role = $this->roles->findOneBy(['code' => $code]) ?? (new Role())->setCode($code);
            $this->em->persist($role->setLibelle($libelle));
        }
        $this->em->flush();
        $io->success(count(self::ROLES).' rôles synchronisés.');

        $username = $input->getOption('admin-username');
        if ($username && !$this->users->findOneBy(['username' => $username])) {
            $password = $input->getOption('admin-password') ?: getenv('ADMIN_PASSWORD');
            if (!$password) {
                $io->error('Mot de passe admin manquant (--admin-password ou ADMIN_PASSWORD).');

                return Command::FAILURE;
            }
            $admin = (new User())
                ->setUsername($username)->setNom('Admin')->setPrenom('Admin')
                ->setEmail($username.'@cn95.local')
                ->setAptitude($this->aptitudes->findOneBy(['code' => 'MF1']))
                ->addUserRole($this->roles->findOneBy(['code' => Role::ADMIN]))
                ->addUserRole($this->roles->findOneBy(['code' => Role::ADHERENT]));
            $admin->setPassword($this->hasher->hashPassword($admin, $password));
            $this->em->persist($admin);
            $this->em->flush();
            $io->success("Administrateur $username créé.");
        }

        return Command::SUCCESS;
    }
}
