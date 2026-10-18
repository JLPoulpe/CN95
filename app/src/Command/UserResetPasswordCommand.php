<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:user:reset-password', description: 'Redéfinit le mot de passe d\'un compte existant (variable USER_PASSWORD)')]
class UserResetPasswordCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('username', InputArgument::REQUIRED, 'Identifiant du compte');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $username = $input->getArgument('username');

        $user = $this->users->findOneBy(['username' => $username]);
        if (!$user) {
            $io->error("Compte $username introuvable.");

            return Command::FAILURE;
        }

        $password = getenv('USER_PASSWORD');
        if (!$password) {
            $io->error('Mot de passe manquant (variable USER_PASSWORD).');

            return Command::FAILURE;
        }

        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->em->flush();
        $io->success("Mot de passe de $username redéfini.");

        return Command::SUCCESS;
    }
}
