<?php

namespace App\Command;

use App\Purge\PurgeEnvironnement;
use App\Purge\PurgeException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Purge de la base, lancée sur chaque environnement par deploy/purge.sh.
 * Sans --force, la commande demande de taper PURGER : elle ne supprime jamais rien en mode non interactif sans --force.
 */
#[AsCommand(name: 'app:purge', description: 'Purge la base : niveau « totale », « configuration » ou « planning » (les administrateurs sont toujours conservés)')]
class PurgeCommand extends Command
{
    private const CONFIRMATION = 'PURGER';

    public function __construct(
        private readonly PurgeEnvironnement $purge,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('niveau', null, InputOption::VALUE_REQUIRED, 'totale : tout sauf administrateurs, rôles, permissions et configuration des mails ; configuration : idem en gardant aussi « Configuration de l\'outil » (aptitudes, lieux, mails, permissions) ; planning : uniquement les semaines et créneaux (lieux et reste conservés)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Affiche ce qui serait supprimé, sans rien supprimer')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ne demande pas la confirmation (obligatoire en mode non interactif)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $niveau = (string) $input->getOption('niveau');
        if (!in_array($niveau, PurgeEnvironnement::NIVEAUX, true)) {
            $io->error(sprintf('Option --niveau obligatoire : %s.', implode(' ou ', PurgeEnvironnement::NIVEAUX)));

            return Command::INVALID;
        }

        $simulation = (bool) $input->getOption('dry-run');

        try {
            $resultat = $this->purge->executer($niveau, true);
        } catch (PurgeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->title(sprintf('Purge de la base, niveau « %s »', $niveau));
        $io->table(['Élément', 'Lignes'], array_map(
            static fn (array $ligne): array => [$ligne['libelle'], $ligne['nombre']],
            $resultat,
        ));
        $io->note(match ($niveau) {
            PurgeEnvironnement::NIVEAU_TOTALE => 'Conservés : administrateurs, rôles, permissions, configuration des mails, historique des migrations.',
            PurgeEnvironnement::NIVEAU_PLANNING => 'Conservés : tout sauf les semaines et créneaux du planning (utilisateurs, aptitudes, lieux d\'activité, activités, invitations, fiches…).',
            default => 'Conservés : administrateurs, rôles, permissions, configuration des mails, aptitudes, lieux d\'activité, historique des migrations.',
        });

        if ($simulation) {
            $io->note('Simulation : aucune donnée supprimée.');

            return Command::SUCCESS;
        }

        if (!$input->getOption('force')) {
            if (!$input->isInteractive()) {
                $io->error('Mode non interactif : ajouter --force pour purger.');

                return Command::FAILURE;
            }

            $saisie = $io->ask(sprintf('Pour confirmer la suppression, tapez %s', self::CONFIRMATION));
            if (self::CONFIRMATION !== $saisie) {
                $io->warning('Purge annulée.');

                return Command::SUCCESS;
            }
        }

        try {
            $this->purge->executer($niveau, false);
        } catch (PurgeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        // Journal sans donnée personnelle : seulement le niveau et des compteurs
        $this->logger->notice('Purge de la base effectuée', [
            'niveau' => $niveau,
            'lignes' => array_sum(array_column($resultat, 'nombre')),
        ]);
        $io->success('Purge effectuée.');

        return Command::SUCCESS;
    }
}
