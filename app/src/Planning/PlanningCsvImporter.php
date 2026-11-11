<?php

namespace App\Planning;

use App\Entity\Activite;
use App\Entity\Aptitude;
use App\Entity\LieuActivite;
use App\Entity\PlanningCreneau;
use App\Entity\PlanningSemaine;
use App\Enum\Jour;
use App\Repository\ActiviteRepository;
use App\Repository\AptitudeRepository;
use App\Repository\LieuActiviteRepository;
use App\Repository\PlanningSemaineRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import du calendrier de saison. Colonnes : Date ; Jour ; <une colonne par aptitude (PN1, PE40…)> ; Événement / remarque.
 *
 * - Chaque cellule d'aptitude contient une activité (« Séance bloc », « Séance PMT »…).
 * - Pour un jour donné, les aptitudes ayant la même activité forment un seul créneau.
 * - Tous les jours de la semaine sont acceptés (séances comme événements).
 * - « T » (cellule seule) vaut « Séance théorie ».
 * - La colonne d'événements (« Événement », « Remarque » ou colonne sans nom : FOSSE, Salon plongée, TIV…) crée un
 *   créneau « Autres » ce jour-là, sans lieu, avec le texte en précision.
 * - La colonne « Vacances » (toute valeur non vide) marque les jours de vacances. Une semaine sans aucune séance dont
 *   le lundi ou le vendredi est en vacances est créée « fermée », motif « Vacances ».
 * - Une semaine déjà présente n'est jamais modifiée.
 * - Les lieux sont déduits de l'activité (voir LIEUX_PAR_ACTIVITE).
 */
class PlanningCsvImporter
{
    /** Colonnes reconnues comme non-aptitude. */
    private const COLONNES_IGNOREES = ['date', 'jour', 'vacances', 'vacance'];

    /** Colonnes d'événements (une colonne sans nom en fait partie). */
    private const COLONNES_EVENEMENT = ['evenement / remarque', 'evenement', 'remarque'];

    private const MOTIF_VACANCES = 'Vacances';

    /**
     * Aptitudes ciblées par colonne. PN2 et PN3 sont des groupes de préparation, pas des aptitudes :
     * PN2 = N1 ou PE40, PN3 = N2. PN1 désigne l'aptitude PN1. Une autre colonne désigne l'aptitude de même code.
     */
    private const APTITUDES_PAR_COLONNE = [
        'PN1' => ['PN1'],
        'PN2' => ['N1', 'PE40'],
        'PN3' => ['N2'],
    ];

    /**
     * Lieux [groupe, nom] par activité (clé : libellé normalisé sans « seance »).
     * Quand deux activités sont le même jour, les lignes partagées (« partage ») ne sont données à aucune.
     */
    private const LIEUX_PAR_ACTIVITE = [
        'bloc' => ['propres' => [['Grand bassin', 'Ligne 1'], ['Grand bassin', 'Ligne 2']], 'partage' => [['Grand bassin', 'Ligne 5'], ['Grand bassin', 'Ligne 6']]],
        'pmt' => ['propres' => [['Grand bassin', 'Ligne 3'], ['Grand bassin', 'Ligne 4']], 'partage' => [['Grand bassin', 'Ligne 5'], ['Grand bassin', 'Ligne 6']]],
        'apnee' => ['propres' => [['Petit bassin', 'Ligne 1'], ['Petit bassin', 'Ligne 2'], ['Petit bassin', 'Ligne 3']], 'partage' => []],
        'theorie' => ['propres' => [['Salles', 'Grande salle']], 'partage' => []],
    ];

    public function __construct(
        private EntityManagerInterface $em,
        private PlanningSemaineRepository $semaines,
        private ActiviteRepository $activites,
        private AptitudeRepository $aptitudes,
        private LieuActiviteRepository $lieux,
    ) {
    }

    /**
     * @return array{semaines: list<string>, creneaux: int, ignored: list<string>, errors: list<string>}
     */
    public function import(string $path, bool $dryRun = false): array
    {
        $result = ['semaines' => [], 'creneaux' => 0, 'ignored' => [], 'errors' => []];

        $content = file_get_contents($path);
        if (false === $content || '' === trim($content)) {
            $result['errors'][] = 'Le fichier est vide.';

            return $result;
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\R/', $content);
        $separator = substr_count((string) $lines[0], ';') >= substr_count((string) $lines[0], ',') ? ';' : ',';
        $header = array_map(fn (string $h) => $this->normalize($h), str_getcsv((string) array_shift($lines), $separator, '"', ''));
        $colDate = array_search('date', $header, true);
        if (false === $colDate) {
            $result['errors'][] = 'Colonne « Date » introuvable dans l\'en-tête.';

            return $result;
        }

        // Colonnes d'aptitude -> entité
        $colAptitudes = [];
        $colEvenement = null;
        $colVacances = null;
        foreach ($header as $i => $name) {
            if (in_array($name, ['vacances', 'vacance'], true)) {
                $colVacances = $i;
            }
            if (null === $colEvenement && ('' === $name || in_array($name, self::COLONNES_EVENEMENT, true))) {
                $colEvenement = $i;
            }
            if ($i === $colDate || in_array($name, self::COLONNES_IGNOREES, true) || in_array($name, self::COLONNES_EVENEMENT, true) || '' === $name) {
                continue;
            }
            $codes = self::APTITUDES_PAR_COLONNE[strtoupper($name)] ?? [strtoupper($name)];
            $colAptitudes[$i] = [];
            foreach ($codes as $code) {
                $aptitude = $this->aptitudes->findOneBy(['code' => $code]);
                if (null === $aptitude) {
                    $result['errors'][] = sprintf('Aptitude « %s » (colonne %d) inconnue.', $code, $i + 1);
                    continue;
                }
                $colAptitudes[$i][] = $aptitude;
            }
        }

        // Référentiels
        $activitesParCle = [];
        foreach ($this->activites->findAll() as $a) {
            foreach ([$a->getLibelle(), $a->getCode()] as $v) {
                if (null !== $v && '' !== $v) {
                    $activitesParCle[$this->cle($v)] = $a;
                }
            }
        }
        $lieuxParCle = [];
        foreach ($this->lieux->findAll() as $l) {
            $lieuxParCle[mb_strtolower($l->getGroupe())."\0".mb_strtolower($l->getNom())] = $l;
        }

        // Lecture : semaine (lundi) -> jour -> activité -> aptitudes
        /** @var array<string, array<string, array<string, array{activite: Activite, aptitudes: array<string, Aptitude>}>>> $plan */
        $plan = [];
        /** @var array<string, list<array{Jour, string}>> $autres Événements par semaine */
        $autres = [];
        /** @var array<string, array<int, true>> $vacances Jours de vacances par semaine (numéro ISO) */
        $vacances = [];
        $activitesManquantes = [];
        foreach ($lines as $i => $line) {
            $n = $i + 2;
            if ('' === trim($line)) {
                continue;
            }
            $row = array_map('trim', str_getcsv($line, $separator, '"', ''));
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $row[$colDate] ?? '');
            if (false === $date || $date->format('Y-m-d') !== ($row[$colDate] ?? '')) {
                $result['errors'][] = sprintf('Ligne %d : date « %s » invalide (AAAA-MM-JJ attendu).', $n, $row[$colDate] ?? '');
                continue;
            }

            $jour = Jour::depuisNumeroIso($date->format('N'));
            $lundi = $date->modify('-'.$jour->decalage().' days')->format('Y-m-d');

            if (null !== $colVacances && '' !== ($row[$colVacances] ?? '')) {
                $vacances[$lundi][(int) $date->format('N')] = true;
            }
            $evenement = null !== $colEvenement ? ($row[$colEvenement] ?? '') : '';
            if ('' !== $evenement) {
                $autres[$lundi][] = [$jour, $evenement];
            }

            $cellules = [];
            foreach ($colAptitudes as $idx => $aptitudes) {
                $v = $row[$idx] ?? '';
                if ('' !== $v) {
                    $cellules[] = [$aptitudes, $v];
                }
            }

            foreach ($cellules as [$aptitudes, $v]) {
                $activite = $activitesParCle[$this->cle($v)] ?? null;
                if (null === $activite) {
                    if (!isset(self::LIEUX_PAR_ACTIVITE[$this->cle($v)])) {
                        $result['ignored'][] = sprintf('%s : valeur « %s » non reconnue, ignorée.', $date->format('d/m/Y'), $v);
                    } else {
                        $activitesManquantes[$v] = true;
                    }
                    continue;
                }
                $plan[$lundi][$jour->value][$this->cle($v)]['activite'] = $activite;
                $plan[$lundi][$jour->value][$this->cle($v)]['aptitudes'] ??= [];
                foreach ($aptitudes as $a) {
                    $plan[$lundi][$jour->value][$this->cle($v)]['aptitudes'][$a->getCode()] = $a;
                }
            }
        }
        foreach (array_keys($activitesManquantes) as $v) {
            $result['errors'][] = sprintf('Activité « %s » introuvable : la créer d\'abord (libellé ou code identique).', $v);
        }
        if ([] !== $result['errors']) {
            return $result;
        }

        // Activité « Autres » : créée si absente (jamais en simulation)
        $activiteAutres = null;
        if ([] !== $autres) {
            $activiteAutres = $activitesParCle[$this->cle(Activite::CODE_AUTRES)] ?? null;
            if (null === $activiteAutres) {
                $activiteAutres = (new Activite())->setCode(Activite::CODE_AUTRES)->setLibelle('Autres')->setOrdre(count($this->activites->findAll()) + 1);
                if (!$dryRun) {
                    $this->em->persist($activiteAutres);
                }
            }
        }

        // Semaines fermées : aucune séance, lundi ou vendredi en vacances
        $fermees = [];
        foreach ($vacances as $lundiStr => $jours) {
            if (!isset($plan[$lundiStr]) && (isset($jours[1]) || isset($jours[5]))) {
                $fermees[$lundiStr] = true;
            }
        }
        foreach ([...array_keys($autres), ...array_keys($fermees)] as $lundiStr) {
            $plan[$lundiStr] ??= [];
        }

        ksort($plan);
        foreach ($plan as $lundiStr => $jours) {
            $lundi = new \DateTimeImmutable($lundiStr);
            if (null !== $this->semaines->findOneBy(['lundi' => $lundi])) {
                $result['ignored'][] = sprintf('Semaine du %s : existe déjà, non modifiée.', $lundi->format('d/m/Y'));
                continue;
            }

            $semaine = (new PlanningSemaine())->setLundi($lundi);
            if (isset($fermees[$lundiStr])) {
                $semaine->setFermee(true)->setMotif(self::MOTIF_VACANCES);
            }
            foreach ($jours as $jourValue => $parActivite) {
                $cles = array_keys($parActivite);
                foreach ($parActivite as $cle => $data) {
                    $creneau = (new PlanningCreneau())->setJour(Jour::from($jourValue));
                    $creneau->addActivite($data['activite']);
                    foreach ($data['aptitudes'] as $a) {
                        $creneau->addAptitude($a);
                    }
                    foreach ($this->lieuxDe($cle, count($cles) > 1) as [$groupe, $nom]) {
                        $lieu = $lieuxParCle[mb_strtolower($groupe)."\0".mb_strtolower($nom)] ?? null;
                        if (null === $lieu) {
                            $result['errors'][] = sprintf('Lieu « %s – %s » introuvable (import des lieux requis).', $groupe, $nom);
                            continue 3;
                        }
                        $creneau->addLieu($lieu);
                    }
                    if (0 === $creneau->getLieux()->count()) {
                        $result['errors'][] = sprintf('Aucun lieu défini pour l\'activité « %s ».', $data['activite']->getLibelle());
                        continue;
                    }
                    $semaine->addCreneau($creneau);
                    ++$result['creneaux'];
                }
            }
            foreach ($autres[$lundiStr] ?? [] as [$jourAutre, $texte]) {
                $creneau = (new PlanningCreneau())->setJour($jourAutre)->setLibelle(mb_substr($texte, 0, 100));
                $creneau->addActivite($activiteAutres);
                $semaine->addCreneau($creneau);
                ++$result['creneaux'];
            }
            $result['semaines'][] = $lundi->format('d/m/Y').(isset($fermees[$lundiStr]) ? ' (fermée : vacances)' : '');
            if (!$dryRun) {
                $this->em->persist($semaine);
            }
        }

        if ([] !== $result['errors']) {
            return $result;
        }
        if (!$dryRun) {
            $this->em->flush();
        }

        return $result;
    }

    /** @return list<array{string, string}> */
    private function lieuxDe(string $cle, bool $plusieursActivites): array
    {
        $def = self::LIEUX_PAR_ACTIVITE[$cle] ?? ['propres' => [], 'partage' => []];

        return $plusieursActivites ? $def['propres'] : [...$def['propres'], ...$def['partage']];
    }

    /**
     * Correctif : ajoute l'aptitude PN1 aux créneaux déjà importés dont la colonne PN1 du CSV donne l'activité ce jour-là.
     * Idempotent, ne crée ni ne supprime aucun créneau. Les semaines absentes de la base sont signalées, pas créées.
     *
     * @return array{ajoutes: int, deja_presents: int, semaines_absentes: list<string>, ignored: list<string>, errors: list<string>}
     */
    public function corrigerPn1(string $contenu, bool $dryRun = false): array
    {
        $result = ['ajoutes' => 0, 'deja_presents' => 0, 'semaines_absentes' => [], 'ignored' => [], 'errors' => []];

        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu) ?? '';
        if (!mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }
        $lines = preg_split('/\R/', $contenu) ?: [];
        $separator = substr_count((string) ($lines[0] ?? ''), ';') >= substr_count((string) ($lines[0] ?? ''), ',') ? ';' : ',';
        $header = array_map(fn (string $h) => $this->normalize($h), str_getcsv((string) array_shift($lines), $separator, '"', ''));
        $colDate = array_search('date', $header, true);
        $colPn1 = array_search('pn1', $header, true);
        if (false === $colDate || false === $colPn1) {
            $result['errors'][] = 'Colonnes « Date » ou « PN1 » introuvables dans l\'en-tête.';

            return $result;
        }

        $aptitude = $this->aptitudes->findOneBy(['code' => 'PN1']);
        if (null === $aptitude) {
            $result['errors'][] = 'Aptitude « PN1 » introuvable en base.';

            return $result;
        }

        $activitesParCle = [];
        foreach ($this->activites->findAll() as $a) {
            foreach ([$a->getLibelle(), $a->getCode()] as $v) {
                if (null !== $v && '' !== $v) {
                    $activitesParCle[$this->cle($v)] = $a;
                }
            }
        }

        foreach ($lines as $i => $line) {
            if ('' === trim($line)) {
                continue;
            }
            $row = array_map('trim', str_getcsv($line, $separator, '"', ''));
            $cellule = $row[$colPn1] ?? '';
            if ('' === $cellule) {
                continue;
            }
            $n = $i + 2;
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $row[$colDate] ?? '');
            if (false === $date || $date->format('Y-m-d') !== ($row[$colDate] ?? '')) {
                $result['errors'][] = sprintf('Ligne %d : date « %s » invalide (AAAA-MM-JJ attendu).', $n, $row[$colDate] ?? '');
                continue;
            }
            $jour = match ($date->format('N')) {
                '1' => Jour::Lundi,
                '5' => Jour::Vendredi,
                default => null,
            };
            if (null === $jour) {
                continue; // même règle que l'import : seuls lundi et vendredi
            }
            $activite = $activitesParCle[$this->cle($cellule)] ?? null;
            if (null === $activite) {
                $result['ignored'][] = sprintf('%s : activité « %s » introuvable, ignorée.', $date->format('d/m/Y'), $cellule);
                continue;
            }
            $lundi = $date->modify('-'.$jour->decalage().' days');
            $semaine = $this->semaines->findOneBy(['lundi' => $lundi]);
            if (null === $semaine) {
                $result['semaines_absentes'][$lundi->format('d/m/Y')] = true;
                continue;
            }

            foreach ($semaine->getCreneaux() as $creneau) {
                if ($creneau->getJour() !== $jour || !$creneau->getActivites()->contains($activite)) {
                    continue;
                }
                if ($creneau->getAptitudes()->contains($aptitude)) {
                    ++$result['deja_presents'];
                    continue;
                }
                $creneau->addAptitude($aptitude);
                ++$result['ajoutes'];
            }
        }

        $result['semaines_absentes'] = array_keys($result['semaines_absentes']);
        if (!$dryRun) {
            $this->em->flush();
        }

        return $result;
    }

    /** « Séance bloc » / « bloc » -> « bloc ». */
    private function cle(string $s): string
    {
        $cle = preg_replace('/^seance\s+/', '', $this->normalize($s));

        return 't' === $cle ? 'theorie' : $cle; // « T » = théorie
    }

    private function normalize(string $s): string
    {
        return str_replace(['é', 'è', 'ê', 'É', '’'], ['e', 'e', 'e', 'e', "'"], mb_strtolower(trim($s)));
    }
}
