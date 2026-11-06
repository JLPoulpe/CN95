<?php

namespace App\Command;

use App\Adherent\AdherentCsvImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Importe les adhérents d'un fichier CSV nettoyé (voir AdherentCsvImporter pour le format et les règles).
 * Aucun mail n'est envoyé : utiliser ensuite app:user:envoyer-activation.
 * Sans --force, la commande demande confirmation ; en mode non interactif elle exige --force.
 */
#[AsCommand(name: 'app:import:adherents', description: 'Importe des adhérents depuis un CSV (rôle Adhérent, activation en attente, aucun mail envoyé)')]
class ImportAdherentsCommand extends Command
{
    public function __construct(private readonly AdherentCsvImporter $importer)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('fichier', InputArgument::REQUIRED, 'Chemin du fichier CSV')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Valide le fichier et affiche les comptes qui seraient créés, sans rien écrire')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Ne demande pas la confirmation (obligatoire en mode non interactif)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $fichier = (string) $input->getArgument('fichier');
        $simulation = (bool) $input->getOption('dry-run');

        if (!is_file($fichier)) {
            $io->error("Fichier introuvable : $fichier");

            return Command::FAILURE;
        }

        // Une première passe en simulation permet de montrer le résultat avant toute écriture
        $apercu = $this->importer->import($fichier, true);
        $this->afficher($io, $apercu, true);

        if ([] !== $apercu['errors']) {
            $io->error('Le fichier contient des erreurs : rien n\'a été importé.');

            return Command::FAILURE;
        }
        if ($simulation) {
            $io->note('Simulation : rien n\'a été écrit.');

            return Command::SUCCESS;
        }
        if ([] === $apercu['created']) {
            $io->success('Rien à importer.');

            return Command::SUCCESS;
        }

        if (!$input->getOption('force')) {
            if (!$input->isInteractive()) {
                $io->error('Mode non interactif : ajouter --force pour importer.');

                return Command::FAILURE;
            }
            if (!$io->confirm(sprintf('Créer %d compte(s) ?', count($apercu['created'])), false)) {
                $io->warning('Import annulé.');

                return Command::SUCCESS;
            }
        }

        $resultat = $this->importer->import($fichier, false);
        $io->success(sprintf('%d compte(s) créé(s), %d ignoré(s). Aucun mail envoyé.', count($resultat['created']), count($resultat['ignored'])));

        return Command::SUCCESS;
    }

    /** @param array{created: list<array<string, string>>, ignored: list<string>, errors: list<string>} $r */
    private function afficher(SymfonyStyle $io, array $r, bool $simulation): void
    {
        if ([] !== $r['created']) {
            $io->section($simulation ? 'Comptes à créer' : 'Comptes créés');
            $io->table(
                ['Identifiant', 'Prénom', 'Nom', 'Email', 'Aptitude'],
                array_map(fn (array $c): array => [$c['username'], $c['prenom'], $c['nom'], $c['email'], $c['aptitude']], $r['created']),
            );
        }
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
