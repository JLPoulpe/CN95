<?php

namespace App\Command;

use App\Planning\PlanningCsvImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Correctif des plannings déjà importés : ajoute l'aptitude PN1 aux créneaux donnés par la colonne PN1 du calendrier.
 * Le CSV se lit sur l'entrée standard (--fichier=-) pour pouvoir être envoyé à distance par deploy/corriger-pn1.sh.
 */
#[AsCommand(name: 'app:planning:corriger-pn1', description: 'Ajoute l\'aptitude PN1 aux créneaux existants donnés par la colonne PN1 du calendrier')]
class PlanningCorrigerPn1Command extends Command
{
    public function __construct(private readonly PlanningCsvImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('fichier', null, InputOption::VALUE_REQUIRED, 'Chemin du CSV de calendrier, ou - pour lire l\'entrée standard')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte les modifications sans rien écrire');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fichier = (string) $input->getOption('fichier');
        if ('' === $fichier) {
            $io->error('Indiquer --fichier=<chemin> ou --fichier=- (entrée standard).');

            return Command::FAILURE;
        }

        $contenu = '-' === $fichier ? (string) stream_get_contents(\STDIN) : @file_get_contents($fichier);
        if (false === $contenu || '' === trim($contenu)) {
            $io->error('Fichier vide ou illisible.');

            return Command::FAILURE;
        }

        $dryRun = (bool) $input->getOption('dry-run');
        $r = $this->importer->corrigerPn1($contenu, $dryRun);

        foreach ($r['errors'] as $erreur) {
            $io->error($erreur);
        }
        if ([] !== $r['errors']) {
            return Command::FAILURE;
        }
        foreach ($r['ignored'] as $message) {
            $io->writeln('  - '.$message);
        }
        if ([] !== $r['semaines_absentes']) {
            $io->warning('Semaines absentes de la base (non modifiées) : '.implode(', ', $r['semaines_absentes']));
        }

        $io->success(sprintf(
            '%s : %d créneau(x) reçoivent PN1, %d l\'ont déjà.',
            $dryRun ? 'Simulation' : 'Terminé',
            $r['ajoutes'],
            $r['deja_presents'],
        ));

        return Command::SUCCESS;
    }
}
