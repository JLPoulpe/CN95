<?php

namespace App\Command;

use App\Entity\Role;
use App\Entity\User;
use App\Mail\RgpdMailer;
use App\Repository\UserRepository;
use App\Rgpd\PurgeAnnuelle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Rappel au bureau avant la purge du 31/08. Lancée chaque jour par cron : n'envoie rien sauf les jours de rappel.
 */
#[AsCommand(name: 'app:rgpd:rappel-purge', description: 'Rappelle au bureau la suppression des données au 31/08 (J-60, J-30, J-7, J-1)')]
class RgpdRappelPurgeCommand extends Command
{
    /** Nombre de jours avant la purge auxquels un rappel est envoyé. */
    public const JOURS_RAPPEL = [60, 30, 7, 1];

    /** Rôles destinataires : le bureau et les administrateurs. */
    private const ROLES_DESTINATAIRES = [Role::BUREAU, Role::ADMIN];

    public function __construct(
        private readonly UserRepository $users,
        private readonly PurgeAnnuelle $purge,
        private readonly RgpdMailer $mailer,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date du jour au format AAAA-MM-JJ (aujourd\'hui par défaut), pour tester')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche les destinataires sans envoyer de mail');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dateOption = $input->getOption('date');
        $aujourdhui = null !== $dateOption
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $dateOption)
            : new \DateTimeImmutable('today');
        if (false === $aujourdhui) {
            $io->error('Date invalide : utilisez le format AAAA-MM-JJ.');

            return Command::INVALID;
        }

        $limite = $this->prochainePurge($aujourdhui);
        $joursRestants = (int) $aujourdhui->diff($limite)->format('%r%a');

        if (!in_array($joursRestants, self::JOURS_RAPPEL, true)) {
            $io->writeln(sprintf('Pas de rappel aujourd\'hui (purge dans %d jours).', $joursRestants));

            return Command::SUCCESS;
        }

        $destinataires = $this->destinataires();
        $compte = $this->purge->executer($limite, true);
        $simulation = (bool) $input->getOption('dry-run');

        $io->title(sprintf('Rappel de purge : J-%d (%s)', $joursRestants, $limite->format('d/m/Y')));
        $io->table(['Élément', 'Nombre'], [
            ['Comptes utilisateurs', $compte['utilisateurs']],
            ['Invitations', $compte['invitations']],
            ['Réponses', $compte['reponses']],
            ['Demandes RGPD', $compte['demandes']],
            ['Destinataires (bureau et administrateurs)', count($destinataires)],
        ]);

        if ($simulation) {
            $io->note('Simulation : aucun mail envoyé.');

            return Command::SUCCESS;
        }

        $envoyes = 0;
        $echecs = 0;
        foreach ($destinataires as $user) {
            if ($this->mailer->rappelPurge($user, $limite, $joursRestants, $compte)) {
                ++$envoyes;
            } else {
                ++$echecs;
            }
        }

        // Journal sans donnée personnelle : seulement des compteurs
        $this->logger->notice('Rappel de purge RGPD envoyé', ['jours' => $joursRestants, 'envoyes' => $envoyes, 'echecs' => $echecs]);
        $io->success(sprintf('%d rappel(s) envoyé(s), %d échec(s).', $envoyes, $echecs));

        return $echecs > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /** Le 31/08 de l'année en cours, ou de l'année suivante s'il est déjà passé. */
    private function prochainePurge(\DateTimeImmutable $jour): \DateTimeImmutable
    {
        $purge = $jour->setDate((int) $jour->format('Y'), 8, 31)->setTime(0, 0);

        return $jour > $purge ? $purge->modify('+1 year') : $purge;
    }

    /** @return list<User> */
    private function destinataires(): array
    {
        return array_values(array_filter(
            $this->users->findAll(),
            fn (User $user): bool => null !== $user->getEmail() && array_any(
                self::ROLES_DESTINATAIRES,
                fn (string $role): bool => $user->hasRole($role),
            ),
        ));
    }
}
