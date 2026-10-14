<?php

namespace App\Command;

use App\Rgpd\PurgeAnnuelle;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Purge annuelle du 31/08 : planifiée sur l'hôte (cron), voir docs/rgpd/05-procedures.md. */
#[AsCommand(name: 'app:rgpd:purger', description: 'Supprime les comptes et invitations créés jusqu\'au 31/08 (purge annuelle RGPD)')]
class RgpdPurgerCommand extends Command
{
    public function __construct(
        private readonly PurgeAnnuelle $purge,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('date', null, InputOption::VALUE_REQUIRED, 'Date limite au format AAAA-MM-JJ (aujourd\'hui par défaut)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait supprimé, sans rien supprimer')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Autorise une purge en dehors du 31/08 (exécution exceptionnelle)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $dateOption = $input->getOption('date');
        $limite = null !== $dateOption
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $dateOption)
            : new \DateTimeImmutable('today');

        if (false === $limite) {
            $io->error('Date invalide : utilisez le format AAAA-MM-JJ.');

            return Command::INVALID;
        }

        if ('08-31' !== $limite->format('m-d') && !$input->getOption('force')) {
            $io->error('La purge n\'a lieu que le 31/08. Utilisez --force pour une exécution exceptionnelle.');

            return Command::FAILURE;
        }

        $simulation = (bool) $input->getOption('dry-run');
        $resultat = $this->purge->executer($limite, true);

        $io->title(sprintf('Purge RGPD : comptes et invitations créés jusqu\'au %s', $limite->format('d/m/Y')));
        $io->table(['Élément', 'Nombre'], [
            ['Invitations', $resultat['invitations']],
            ['Réponses aux invitations', $resultat['reponses']],
            ['Comptes utilisateurs (administrateurs exclus)', $resultat['utilisateurs']],
            ['Demandes RGPD', $resultat['demandes']],
        ]);

        if ($simulation) {
            $io->note('Simulation : aucune donnée supprimée.');

            return Command::SUCCESS;
        }

        if ($input->isInteractive() && !$io->confirm('Supprimer définitivement ces données ?', false)) {
            $io->warning('Purge annulée.');

            return Command::SUCCESS;
        }

        $this->purge->executer($limite, false);

        // Journal sans donnée personnelle : seulement des compteurs
        $this->logger->notice('Purge RGPD effectuée', [
            'limite' => $limite->format('Y-m-d'),
            'invitations' => $resultat['invitations'],
            'reponses' => $resultat['reponses'],
            'utilisateurs' => $resultat['utilisateurs'],
            'demandes' => $resultat['demandes'],
        ]);
        $io->success('Purge effectuée.');

        return Command::SUCCESS;
    }
}
