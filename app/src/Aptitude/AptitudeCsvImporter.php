<?php

namespace App\Aptitude;

use App\Entity\Aptitude;
use App\Repository\AptitudeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import CSV des aptitudes. Colonnes : code ; libellé (optionnel) ; ordre (optionnel).
 * Les aptitudes existantes (même code, insensible à la casse) sont mises à jour, les autres créées.
 */
class AptitudeCsvImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private AptitudeRepository $aptitudes,
    ) {
    }

    /**
     * Avec $simulation = true, le fichier est validé et les compteurs calculés, sans rien modifier en base.
     *
     * @return array{created: int, updated: int, errors: list<string>}
     */
    public function import(string $path, bool $simulation = false): array
    {
        $result = ['created' => 0, 'updated' => 0, 'errors' => []];

        $content = file_get_contents($path);
        if ($content === false || trim($content) === '') {
            $result['errors'][] = 'Le fichier est vide.';

            return $result;
        }

        // BOM + encodage (Excel exporte souvent en Windows-1252)
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $lines = preg_split('/\R/', $content);
        $firstLine = (string) ($lines[0] ?? '');
        $separator = substr_count($firstLine, ';') >= substr_count($firstLine, ',') ? ';' : ',';

        $existing = [];
        foreach ($this->aptitudes->findAll() as $a) {
            $existing[mb_strtolower((string) $a->getCode())] = $a;
        }
        $nextOrdre = count($existing);
        $seen = [];
        $cols = ['code' => 0, 'libelle' => 1, 'ordre' => 2]; // positions par défaut (sans en-tête)

        foreach ($lines as $i => $line) {
            $n = $i + 1;
            if (trim($line) === '') {
                continue;
            }
            $row = array_map('trim', str_getcsv($line, $separator, '"', ''));

            if ($i === 0 && in_array($this->normalize($row[0] ?? ''), ['code', 'aptitude', 'aptitudes'], true)) {
                // En-tête : repérage des colonnes par leur nom, les autres colonnes sont ignorées
                $cols = ['code' => 0, 'libelle' => null, 'ordre' => null];
                foreach ($row as $idx => $name) {
                    $name = $this->normalize($name);
                    if (in_array($name, ['code', 'aptitude', 'aptitudes'], true) && $idx === 0) {
                        $cols['code'] = $idx;
                    } elseif ($name === 'libelle') {
                        $cols['libelle'] = $idx;
                    } elseif (in_array($name, ['ordre', 'ordre d\'affichage'], true)) {
                        $cols['ordre'] = $idx;
                    }
                }
                continue;
            }
            $code = $row[$cols['code']] ?? '';
            if ($code === '') {
                $result['errors'][] = sprintf('Ligne %d : code manquant.', $n);
                continue;
            }
            if (mb_strlen($code) > 20) {
                $result['errors'][] = sprintf('Ligne %d : code « %s » trop long (20 caractères max).', $n, $code);
                continue;
            }
            $key = mb_strtolower($code);
            if (isset($seen[$key])) {
                $result['errors'][] = sprintf('Ligne %d : code « %s » en doublon dans le fichier (ignoré).', $n, $code);
                continue;
            }
            $seen[$key] = true;

            $libelle = $cols['libelle'] !== null && ($row[$cols['libelle']] ?? '') !== '' ? $row[$cols['libelle']] : null;
            if ($libelle !== null && mb_strlen($libelle) > 100) {
                $result['errors'][] = sprintf('Ligne %d : libellé trop long (100 caractères max).', $n);
                continue;
            }
            $ordreRaw = $cols['ordre'] !== null ? ($row[$cols['ordre']] ?? '') : '';
            if ($ordreRaw !== '' && !ctype_digit($ordreRaw)) {
                $result['errors'][] = sprintf('Ligne %d : ordre « %s » invalide (entier attendu).', $n, $ordreRaw);
                continue;
            }

            $aptitude = $existing[$key] ?? null;
            if ($simulation) {
                if ($aptitude === null) {
                    ++$result['created'];
                } else {
                    ++$result['updated'];
                }
                continue;
            }
            if ($aptitude === null) {
                $aptitude = (new Aptitude())->setCode($code)->setOrdre($ordreRaw !== '' ? (int) $ordreRaw : ++$nextOrdre);
                $existing[$key] = $aptitude;
                ++$result['created'];
            } else {
                if ($ordreRaw !== '') {
                    $aptitude->setOrdre((int) $ordreRaw);
                }
                ++$result['updated'];
            }
            if ($libelle !== null) {
                $aptitude->setLibelle($libelle);
            }
            $this->em->persist($aptitude);
        }

        if (!$simulation) {
            $this->em->flush();
        }

        return $result;
    }

    private function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['é', 'è', 'ê', '’'], ['e', 'e', 'e', "'"], $s);

        return $s;
    }
}
