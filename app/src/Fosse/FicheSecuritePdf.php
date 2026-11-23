<?php

namespace App\Fosse;

use App\Entity\FicheSecurite;
use App\Entity\Palanquee;
use App\Entity\PalanqueeMembre;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/** Génère le PDF de la « Fiche de sécurité club » UCPA à partir de la fiche de sécurité d'une invitation. */
class FicheSecuritePdf
{
    /** Nom du club imprimé sur la fiche. */
    public const CLUB = 'CN95 Plongée';
    /** Palanquées par feuille : 3 lignes de 3 blocs, comme sur le formulaire papier. */
    public const PAR_FEUILLE = 9;
    /** Lignes de plongeurs minimales d'un bloc (le formulaire en prévoit 4). */
    private const LIGNES_PLONGEURS = 4;

    public function __construct(private readonly Environment $twig)
    {
    }

    public function generer(FicheSecurite $fiche): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', false);
        $options->set('tempDir', sys_get_temp_dir());

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($this->twig->render('admin/fiche_securite/pdf.html.twig', $this->donnees($fiche)), 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /** @return array<string, mixed> */
    public function donnees(FicheSecurite $fiche): array
    {
        $invitation = $fiche->getInvitation();
        $blocs = [];
        $adultes = $mineurs = 0;
        foreach ($fiche->getPalanquees() as $palanquee) {
            $blocs[] = $this->bloc($palanquee);
            foreach ($palanquee->getMembres() as $membre) {
                $membre->getUser()?->estMineur($invitation->getDate()) ? ++$mineurs : ++$adultes;
            }
        }

        // Une feuille = 9 blocs ; les blocs manquants restent vides comme sur le papier
        $feuilles = array_chunk($blocs, self::PAR_FEUILLE) ?: [[]];
        foreach ($feuilles as &$feuille) {
            $feuille = array_chunk(array_pad($feuille, self::PAR_FEUILLE, $this->blocVide()), 3);
        }

        $dp = $invitation->getDp();

        return [
            'club' => self::CLUB,
            'date' => $invitation->getDate(),
            'dp' => $dp ? trim($dp->getPrenom().' '.mb_strtoupper((string) $dp->getNom())) : '',
            'feuilles' => $feuilles,
            'adultes' => $adultes,
            'mineurs' => $mineurs,
            'effectif' => $adultes + $mineurs,
        ];
    }

    /** @return array{encadrant: string, niveau: string, plongeurs: list<array{nom: string, niveau: string}>, profondeur: ?int, duree: ?int} */
    private function bloc(Palanquee $palanquee): array
    {
        $chef = $palanquee->getChefMembre();
        $plongeurs = [];
        foreach ($palanquee->getMembresOrdonnes() as $membre) {
            if ($membre !== $chef) {
                $plongeurs[] = ['nom' => $this->nom($membre), 'niveau' => $this->niveau($membre)];
            }
        }
        while (count($plongeurs) < self::LIGNES_PLONGEURS) {
            $plongeurs[] = ['nom' => '', 'niveau' => ''];
        }

        return [
            'encadrant' => $chef ? $this->nom($chef) : '',
            'niveau' => $chef ? $this->niveau($chef) : '',
            'plongeurs' => $plongeurs,
            'profondeur' => $palanquee->getProfondeurMax(),
            'duree' => $palanquee->getDuree(),
        ];
    }

    /** @return array{encadrant: string, niveau: string, plongeurs: list<array{nom: string, niveau: string}>, profondeur: ?int, duree: ?int} */
    private function blocVide(): array
    {
        return [
            'encadrant' => '',
            'niveau' => '',
            'plongeurs' => array_fill(0, self::LIGNES_PLONGEURS, ['nom' => '', 'niveau' => '']),
            'profondeur' => null,
            'duree' => null,
        ];
    }

    private function nom(PalanqueeMembre $membre): string
    {
        $user = $membre->getUser();

        return $user ? trim($user->getPrenom().' '.mb_strtoupper((string) $user->getNom())) : $membre->getLibelle();
    }

    /** Aptitude acquise du participant (le niveau préparé n'apparaît pas sur la fiche). */
    private function niveau(PalanqueeMembre $membre): string
    {
        return (string) $membre->getUser()?->getAptitude()?->getCode();
    }
}
