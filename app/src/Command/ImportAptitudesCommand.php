<?php

namespace App\Command;

use App\Aptitude\AptitudeCsvImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les aptitudes d'un fichier CSV (voir AptitudeCsvImporter pour le format et les règles).
 * Le fichier est validé en entier avant toute écriture : s'il contient une erreur, rien n'est importé.
 * Sans --force, la commande demande confirmation ; en mode non interactif elle exige --force.
 */
#[AsCommand(name: 'app:import:aptitudes', description: 'Importe ou met à jour des aptitudes depuis un CSV (code ; libellé ; ordre)')]
class ImportAptitudesCommand extends Command
{
    public function __construct(private readonly AptitudeCsvImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du fichier CSV')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Valide le fichier et affiche le résultat prévu, sans rien écrire')
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

        // Passe de simulation : validation complète et compteurs, sans écriture
        $apercu = $this->importer->import($fichier, true);

        if ([] !== $apercu['errors']) {
            $io->section('Erreurs');
            $io->listing($apercu['errors']);
            $io->error('Le fichier contient des erreurs : rien n\'a été importé.');

            return Command::FAILURE;
        }

        $io->text(sprintf('%d aptitude(s) à créer, %d à mettre à jour.', $apercu['created'], $apercu['updated']));

        if ($input->getOption('dry-run')) {
            $io->note('Simulation : rien n\'a été écrit.');

            return Command::SUCCESS;
        }
        if ($apercu['created'] + $apercu['updated'] === 0) {
            $io->success('Rien à importer.');

            return Command::SUCCESS;
        }

        if (!$input->getOption('force')) {
            if (!$input->isInteractive()) {
                $io->error('Mode non interactif : ajouter --force pour importer.');

                return Command::FAILURE;
            }
            if (!$io->confirm('Importer ces aptitudes ?', false)) {
                $io->warning('Import annulé.');

                return Command::SUCCESS;
            }
        }

        $resultat = $this->importer->import($fichier);
        $io->success(sprintf('Import terminé : %d créée(s), %d mise(s) à jour.', $resultat['created'], $resultat['updated']));

        return Command::SUCCESS;
    }
}
