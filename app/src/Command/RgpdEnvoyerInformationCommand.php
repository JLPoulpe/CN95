<?php

namespace App\Command;

use App\Entity\DemandeRgpd;
use App\Enum\TypeDemandeRgpd;
use App\Mail\RgpdMailer;
use App\Repository\DemandeRgpdRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Envoie aux membres existants le mail d'information RGPD (accusé de lecture).
 * Une seule fois par version du texte : relancer la commande n'envoie pas de doublon.
 * Les nouveaux comptes reçoivent cette information dans leur mail d'activation et l'acquittent en choisissant leur mot de passe :
 * ils ne sont pas concernés tant que leur activation est en attente, ni une fois qu'elle est faite pour cette version.
 */
#[AsCommand(name: 'app:rgpd:envoyer-information', description: 'Envoie le mail d\'information RGPD aux membres (une fois par version du texte)')]
class RgpdEnvoyerInformationCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DemandeRgpdRepository $demandes,
        private readonly EntityManagerInterface $em,
        private readonly RgpdMailer $mailer,
        private readonly UrlGeneratorInterface $urls,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Compte les destinataires sans rien envoyer')
            ->addOption('representants', null, InputOption::VALUE_REQUIRED, 'E-mails des membres mineurs, séparés par des virgules : leur mail est adressé à leurs représentants légaux');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $simulation = (bool) $input->getOption('dry-run');
        $representants = array_map(
            fn (string $e): string => mb_strtolower(trim($e)),
            array_filter(explode(',', (string) $input->getOption('representants'))),
        );
        $version = RgpdMailer::VERSION_TEXTE;

        $envoyes = 0;
        $echecs = 0;
        $dejaEnvoyes = 0;
        $enAttente = 0;

        foreach ($this->users->findAll() as $user) {
            if ($this->demandes->existePourVersion($user, TypeDemandeRgpd::Information, $version)) {
                ++$dejaEnvoyes;
                continue;
            }

            // Mail d'activation déjà envoyé : il contient l'information, qui sera acquittée à la création du mot de passe
            if ($user->activationEnAttente()) {
                ++$enAttente;
                continue;
            }

            if ($simulation) {
                ++$envoyes;
                continue;
            }

            $estRepresentant = in_array(mb_strtolower((string) $user->getEmail()), $representants, true);
            $information = new DemandeRgpd($user, TypeDemandeRgpd::Information, $version);
            $this->em->persist($information);
            $this->em->flush();

            $liens = [
                'accuse' => $this->urls->generate('rgpd_accuse', ['jeton' => $information->getJeton()], UrlGeneratorInterface::ABSOLUTE_URL),
                'politique' => $this->urls->generate('app_confidentialite', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ];

            if ($this->mailer->information($user, $liens, $estRepresentant)) {
                ++$envoyes;
            } else {
                // Sans mail reçu, la demande ne doit pas rester en attente : on la retire pour pouvoir renvoyer plus tard
                $this->em->remove($information);
                $this->em->flush();
                ++$echecs;
            }
        }

        $io->title(sprintf('Information RGPD, version %s', $version));
        $io->table(['Résultat', 'Membres'], [
            [$simulation ? 'À envoyer' : 'Envoyés', $envoyes],
            ['Échecs d\'envoi', $echecs],
            ['Déjà informés pour cette version', $dejaEnvoyes],
            ['Activation en attente (information dans le mail d\'activation)', $enAttente],
        ]);

        // Journal sans donnée personnelle : seulement des compteurs
        $this->logger->notice('Information RGPD envoyée', ['version' => $version, 'envoyes' => $envoyes, 'echecs' => $echecs, 'simulation' => $simulation]);

        if ($simulation) {
            $io->note('Simulation : aucun mail envoyé.');
        }

        return $echecs > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
