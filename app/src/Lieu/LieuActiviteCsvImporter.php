<?php

namespace App\Lieu;

use App\Entity\LieuActivite;
use App\Repository\LieuActiviteRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Import CSV des lieux d'activité. Colonnes : groupe ; nom ; ordre (optionnel).
 * Un lieu existant (même groupe et même nom, insensible à la casse) est mis à jour, sinon il est créé.
 */
class LieuActiviteCsvImporter
{
    public function __construct(
        private EntityManagerInterface $em,
        private LieuActiviteRepository $lieux,
    ) {
    }

    /**
     * Avec $simulation = true, le fichier est validé et les compteurs calculés, sans rien persister ni écrire en base.
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

        $key = fn (string $groupe, string $nom): string => mb_strtolower($groupe)."\0".mb_strtolower($nom);
        $existing = [];
        foreach ($this->lieux->findAll() as $l) {
            $existing[$key($l->getGroupe(), $l->getNom())] = $l;
        }
        $seen = [];
        $cols = ['groupe' => 0, 'nom' => 1, 'ordre' => 2]; // positions par défaut (sans en-tête)

        foreach ($lines as $i => $line) {
            $n = $i + 1;
            if (trim($line) === '') {
                continue;
            }
            $row = array_map('trim', str_getcsv($line, $separator, '"', ''));

            if ($i === 0 && $this->normalize($row[0] ?? '') === 'groupe') {
                // En-tête : repérage des colonnes par leur nom, les autres colonnes sont ignorées
                $cols = ['groupe' => 0, 'nom' => null, 'ordre' => null];
                foreach ($row as $idx => $name) {
                    $name = $this->normalize($name);
                    if ($name === 'groupe') {
                        $cols['groupe'] = $idx;
                    } elseif ($name === 'nom') {
                        $cols['nom'] = $idx;
                    } elseif (in_array($name, ['ordre', 'ordre d\'affichage'], true)) {
                        $cols['ordre'] = $idx;
                    }
                }
                if ($cols['nom'] === null) {
                    $result['errors'][] = 'Colonne « nom » introuvable dans l\'en-tête.';

                    return $result;
                }
                continue;
            }

            $groupe = $row[$cols['groupe']] ?? '';
            $nom = $row[$cols['nom']] ?? '';
            if ($groupe === '' || $nom === '') {
                $result['errors'][] = sprintf('Ligne %d : groupe ou nom manquant.', $n);
                continue;
            }
            if (mb_strlen($groupe) > 100 || mb_strlen($nom) > 100) {
                $result['errors'][] = sprintf('Ligne %d : groupe ou nom trop long (100 caractères max).', $n);
                continue;
            }
            $ordreRaw = $cols['ordre'] !== null ? ($row[$cols['ordre']] ?? '') : '';
            if ($ordreRaw !== '' && !ctype_digit($ordreRaw)) {
                $result['errors'][] = sprintf('Ligne %d : ordre « %s » invalide (entier attendu).', $n, $ordreRaw);
                continue;
            }
            $k = $key($groupe, $nom);
            if (isset($seen[$k])) {
                $result['errors'][] = sprintf('Ligne %d : « %s – %s » en doublon dans le fichier (ignoré).', $n, $groupe, $nom);
                continue;
            }
            $seen[$k] = true;

            $lieu = $existing[$k] ?? null;
            if ($lieu === null) {
                $lieu = (new LieuActivite())->setGroupe($groupe)->setNom($nom)->setOrdre($ordreRaw !== '' ? (int) $ordreRaw : 0);
                $existing[$k] = $lieu;
                ++$result['created'];
            } else {
                if ($ordreRaw !== '') {
                    $lieu->setOrdre((int) $ordreRaw);
                }
                ++$result['updated'];
            }
            if (!$simulation) {
                $this->em->persist($lieu);
            }
        }

        if (!$simulation) {
            $this->em->flush();
        }

        return $result;
    }

    private function normalize(string $s): string
    {
        return str_replace(['é', 'è', 'ê', '’'], ['e', 'e', 'e', "'"], mb_strtolower(trim($s)));
    }
}
