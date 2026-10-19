<?php

namespace App\Command;

use App\Mail\MailConfigurationManager;
use App\Mail\MailException;
use App\Mail\Plugin\MailPluginRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Affiche en clair la configuration d'envoi de mails de l'environnement courant (clé API Brevo comprise).
 * La configuration est chiffrée en base avec une clé dérivée de APP_SECRET : seul l'environnement qui l'a enregistrée
 * peut la déchiffrer, d'où une commande à lancer sur chaque environnement (voir deploy/brevo-key.sh).
 */
#[AsCommand(name: 'app:mail:afficher-config', description: 'Affiche en clair la configuration d\'envoi de mails (clé API comprise)')]
class MailAfficherConfigCommand extends Command
{
    public function __construct(
        private readonly MailConfigurationManager $configurations,
        private readonly MailPluginRegistry $plugins,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('masquer', null, InputOption::VALUE_NONE, 'Masque les valeurs secrètes (ne garde que les 4 derniers caractères)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration = $this->configurations->courante();
        if (null === $configuration) {
            $output->writeln('Aucun système d\'envoi de mails configuré.');

            return Command::SUCCESS;
        }

        try {
            $valeurs = $this->configurations->configPlugin($configuration);
        } catch (MailException|\JsonException $e) {
            // Le plus souvent : APP_SECRET différent de celui qui a servi à enregistrer la configuration
            $output->writeln('<error>Configuration illisible : '.$e->getMessage().'</error>');

            return Command::FAILURE;
        }

        $plugin = $this->plugins->all()[$configuration->getPluginCode()] ?? null;
        $output->writeln(sprintf('Plugin     : %s (%s)', $plugin?->label() ?? '?', $configuration->getPluginCode()));
        $output->writeln(sprintf('Expéditeur : %s <%s>', $configuration->getExpediteurNom(), $configuration->getExpediteurEmail()));

        foreach ($plugin?->configFields() ?? [] as $code => $champ) {
            $valeur = (string) ($valeurs[$code] ?? '');
            if ($input->getOption('masquer') && ($champ['secret'] ?? false)) {
                $valeur = '' === $valeur ? '' : '…'.substr($valeur, -4);
            }
            $output->writeln(mb_str_pad($champ['label'], 10).' : '.$valeur, OutputInterface::OUTPUT_RAW);
        }

        return Command::SUCCESS;
    }
}
