<?php

namespace App\Command;

use App\Planning\PlanningCsvImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe le calendrier de saison (voir PlanningCsvImporter pour le format et les règles).
 * Prérequis : aptitudes, activités (bloc, PMT, apnée, théorie) et lieux déjà créés.
 */
#[AsCommand(name: 'app:import:planning', description: 'Importe le planning (semaines et créneaux) depuis un CSV de calendrier')]
class ImportPlanningCommand extends Command
{
    public function __construct(private readonly PlanningCsvImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du fichier CSV')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Valide le fichier sans rien écrire')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ne demande pas la confirmation (obligatoire en mode non interactif)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fichier = (string) $input->getArgument('fichier');
        if (!is_file($fichier)) {
            $io->error("Fichier introuvable : $fichier");

            return Command::FAILURE;
        }

        $apercu = $this->importer->import($fichier, true);
        $this->afficher($io, $apercu);
        if ([] !== $apercu['errors']) {
            $io->error('Le fichier contient des erreurs : rien n\'a été importé.');

            return Command::FAILURE;
        }
        if ($input->getOption('dry-run')) {
            $io->note('Simulation : rien n\'a été écrit.');

            return Command::SUCCESS;
        }
        if ([] === $apercu['semaines']) {
            $io->success('Rien à importer.');

            return Command::SUCCESS;
        }
        if (!$input->getOption('force')) {
            if (!$input->isInteractive()) {
                $io->error('Mode non interactif : ajouter --force pour importer.');

                return Command::FAILURE;
            }
            if (!$io->confirm(sprintf('Créer %d semaine(s) et %d créneau(x) ?', count($apercu['semaines']), $apercu['creneaux']), false)) {
                $io->warning('Import annulé.');

                return Command::SUCCESS;
            }
        }

        $r = $this->importer->import($fichier, false);
        $io->success(sprintf('%d semaine(s) et %d créneau(x) créés.', count($r['semaines']), $r['creneaux']));

        return Command::SUCCESS;
    }

    /** @param array{semaines: list<string>, creneaux: int, ignored: list<string>, errors: list<string>} $r */
    private function afficher(SymfonyStyle $io, array $r): void
    {
        $io->writeln(sprintf('%d semaine(s) à créer, %d créneau(x).', count($r['semaines']), $r['creneaux']));
        if ([] !== $r['ignored']) {
            $io->section('Ignorés');
            $io->listing($r['ignored']);
        }
        if ([] !== $r['errors']) {
            $io->section('Erreurs');
            $io->listing($r['errors']);
        }
    }
}
