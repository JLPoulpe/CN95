<?php

namespace App\Command;

use App\Entity\User;
use App\Mail\CompteMailer;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Envoie le mail d'activation de compte (le même que lors de l'ajout d'un adhérent) aux comptes dont l'activation est en attente.
 * Un nouveau lien est généré à chaque envoi : le précédent n'est plus valable.
 *
 * Il faut choisir explicitement la cible : --email=<adresse> pour une seule personne, ou --tous pour tous les comptes en attente.
 * Les comptes déjà activés ne reçoivent jamais de mail.
 */
#[AsCommand(name: 'app:user:envoyer-activation', description: 'Envoie le mail d\'activation à un compte (--email) ou à tous les comptes en attente (--tous)')]
class UserEnvoyerActivationCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly CompteMailer $mailer,
        private readonly UrlGeneratorInterface $urls,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Adresse email du compte à qui envoyer le mail')
            ->addOption('tous', null, InputOption::VALUE_NONE, 'Envoie le mail à tous les comptes dont l\'activation est en attente')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Liste les destinataires sans rien envoyer')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Avec --tous : ne demande pas la confirmation (obligatoire en mode non interactif)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = trim((string) $input->getOption('email'));
        $tous = (bool) $input->getOption('tous');
        $simulation = (bool) $input->getOption('dry-run');

        if ($tous === ('' !== $email)) {
            $io->error('Choisir exactement une cible : --email=<adresse> ou --tous.');

            return Command::FAILURE;
        }

        if ('' !== $email) {
            $user = $this->users->findOneBy(['email' => mb_strtolower($email)]);
            if (null === $user) {
                $io->error("Aucun compte avec l'email $email.");

                return Command::FAILURE;
            }
            if (!$user->activationEnAttente()) {
                $io->error("Le compte $email est déjà activé (ou sans activation en attente) : aucun mail envoyé.");

                return Command::FAILURE;
            }
            $destinataires = [$user];
        } else {
            $destinataires = array_values(array_filter($this->users->findAll(), fn (User $u): bool => $u->activationEnAttente()));
        }

        $io->table(['Identifiant', 'Nom', 'Email'], array_map(fn (User $u): array => [$u->getUsername(), $u->getNomComplet(), $u->getEmail()], $destinataires));
        if ([] === $destinataires) {
            $io->success('Aucun compte en attente d\'activation.');

            return Command::SUCCESS;
        }
        if ($simulation) {
            $io->note(sprintf('Simulation : %d mail(s) auraient été envoyés.', count($destinataires)));

            return Command::SUCCESS;
        }

        if ($tous && !$input->getOption('force')) {
            if (!$input->isInteractive()) {
                $io->error('Mode non interactif : ajouter --force pour envoyer à tous.');

                return Command::FAILURE;
            }
            if (!$io->confirm(sprintf('Envoyer %d mail(s) d\'activation ?', count($destinataires)), false)) {
                $io->warning('Envoi annulé.');

                return Command::SUCCESS;
            }
        }

        $envoyes = 0;
        $echecs = [];
        foreach ($destinataires as $user) {
            // Le jeton doit être en base avant l'envoi : le lien est valable dès réception du mail
            $jeton = $user->genererActivation(new \DateTimeImmutable());
            $this->em->flush();
            $lien = $this->urls->generate('compte_activation', ['token' => $jeton], UrlGeneratorInterface::ABSOLUTE_URL);
            if ($this->mailer->activation($user, $lien)) {
                ++$envoyes;
            } else {
                $echecs[] = (string) $user->getEmail();
            }
        }

        $io->success(sprintf('%d mail(s) envoyé(s).', $envoyes));
        if ([] !== $echecs) {
            $io->error('Échec d\'envoi pour : '.implode(', ', $echecs));

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
