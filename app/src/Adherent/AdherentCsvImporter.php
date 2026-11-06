<?php

namespace App\Adherent;

use App\Entity\Role;
use App\Entity\User;
use App\Repository\AptitudeRepository;
use App\Repository\RoleRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Import CSV des adhérents (séparateur « ; », UTF-8).
 * Colonnes : nom ; prenom ; email ; telephone ; date_naissance (AAAA-MM-JJ) ; caci_expire_le (AAAA-MM-JJ) ; aptitude (code).
 *
 * Les données doivent déjà être nettoyées : le fichier est validé, pas corrigé. Une ligne invalide est signalée et ignorée.
 * Une ligne dont l'email existe déjà en base est ignorée (l'import peut être relancé sans doublon).
 *
 * Chaque compte créé reçoit le rôle Adhérent, un mot de passe aléatoire inutilisable et une activation « en attente » :
 * aucun mail n'est envoyé ici (voir app:user:envoyer-activation).
 *
 * Identifiant : le prénom (minuscules, sans accent). Si ce prénom est porté par plusieurs lignes du fichier ou existe déjà
 * en base, l'identifiant devient prénom.nom (et un numéro est ajouté en dernier recours).
 */
class AdherentCsvImporter
{
    private const COLONNES = ['nom', 'prenom', 'email', 'telephone', 'date_naissance', 'caci_expire_le', 'aptitude'];

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private AptitudeRepository $aptitudes,
        private RoleRepository $roles,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    /**
     * @return array{created: list<array{username: string, nom: string, prenom: string, email: string, aptitude: string}>, ignored: list<string>, errors: list<string>}
     */
    public function import(string $path, bool $simulation = false): array
    {
        $result = ['created' => [], 'ignored' => [], 'errors' => []];

        $content = @file_get_contents($path);
        if (false === $content || '' === trim($content)) {
            $result['errors'][] = 'Le fichier est vide ou illisible.';

            return $result;
        }
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        if (!mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $role = $this->roles->findOneBy(['code' => Role::ADHERENT]);
        if (null === $role) {
            $result['errors'][] = 'Le rôle Adhérent est introuvable : lancer app:seed.';

            return $result;
        }

        $lignes = preg_split('/\R/', $content);
        $entete = array_map(fn (string $c): string => mb_strtolower(trim($c)), str_getcsv((string) array_shift($lignes), ';', '"', ''));
        $manquantes = array_diff(self::COLONNES, $entete);
        if ([] !== $manquantes) {
            $result['errors'][] = 'Colonnes manquantes : '.implode(', ', $manquantes).'.';

            return $result;
        }
        $position = array_flip($entete);

        $emailsExistants = [];
        $usernamesPris = [];
        foreach ($this->users->findAll() as $u) {
            $emailsExistants[mb_strtolower((string) $u->getEmail())] = true;
            $usernamesPris[mb_strtolower((string) $u->getUsername())] = true;
        }

        // Passe 1 : validation des lignes
        $retenues = [];
        $emailsFichier = [];
        foreach ($lignes as $i => $ligne) {
            if ('' === trim($ligne)) {
                continue;
            }
            $n = $i + 2;
            $cellules = array_map('trim', str_getcsv($ligne, ';', '"', ''));
            $v = [];
            foreach (self::COLONNES as $col) {
                $v[$col] = $cellules[$position[$col]] ?? '';
            }

            $email = mb_strtolower($v['email']);
            $erreur = $this->valider($v, $email);
            if (null !== $erreur) {
                $result['errors'][] = sprintf('Ligne %d (%s %s) : %s', $n, $v['prenom'], $v['nom'], $erreur);
                continue;
            }
            if (isset($emailsExistants[$email])) {
                $result['ignored'][] = sprintf('Ligne %d (%s %s) : email déjà présent en base.', $n, $v['prenom'], $v['nom']);
                continue;
            }
            if (isset($emailsFichier[$email])) {
                $result['errors'][] = sprintf('Ligne %d (%s %s) : email en double dans le fichier.', $n, $v['prenom'], $v['nom']);
                continue;
            }
            $emailsFichier[$email] = true;
            $v['email'] = $email;
            $retenues[] = $v;
        }

        // Passe 2 : identifiants
        $parPrenom = [];
        foreach ($retenues as $v) {
            $slug = $this->slug($v['prenom']);
            $parPrenom[$slug] = ($parPrenom[$slug] ?? 0) + 1;
        }
        $nouveaux = [];
        foreach ($retenues as $v) {
            $slug = $this->slug($v['prenom']);
            $username = $slug;
            if ($parPrenom[$slug] > 1 || isset($usernamesPris[$username])) {
                $username = $slug.'.'.$this->slug($v['nom']);
            }
            $base = $username;
            for ($k = 2; isset($usernamesPris[$username]); ++$k) {
                $username = $base.$k;
            }
            $usernamesPris[$username] = true;

            $user = (new User())
                ->setUsername($username)
                ->setNom($v['nom'])
                ->setPrenom($v['prenom'])
                ->setEmail($v['email'])
                ->setTelephone('' !== $v['telephone'] ? $v['telephone'] : null)
                ->setDateNaissance($this->date($v['date_naissance']))
                ->setCaciExpireLe($this->date($v['caci_expire_le']))
                ->setAptitude($this->aptitudes->findOneBy(['code' => $v['aptitude']]))
                ->addUserRole($role);
            // Mot de passe aléatoire inutilisable ; jeton d'activation en attente, le clair est volontairement perdu (renouvelé à l'envoi du mail)
            $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));
            $user->genererActivation(new \DateTimeImmutable());
            $nouveaux[] = $user;
            $result['created'][] = ['username' => $username, 'nom' => $v['nom'], 'prenom' => $v['prenom'], 'email' => $v['email'], 'aptitude' => $v['aptitude']];
        }

        if (!$simulation && [] !== $nouveaux) {
            foreach ($nouveaux as $user) {
                $this->em->persist($user);
            }
            $this->em->flush();
        }

        return $result;
    }

    /** @param array<string, string> $v */
    private function valider(array $v, string $email): ?string
    {
        if ('' === $v['nom'] || '' === $v['prenom']) {
            return 'nom ou prénom manquant.';
        }
        if (false === filter_var($email, \FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 180) {
            return 'email invalide.';
        }
        if ('' !== $v['telephone'] && !preg_match('/^\+?[0-9]{6,19}$/', $v['telephone'])) {
            return 'téléphone invalide (chiffres uniquement attendus).';
        }
        foreach (['date_naissance', 'caci_expire_le'] as $col) {
            if ('' !== $v[$col] && null === $this->date($v[$col])) {
                return sprintf('%s invalide (format AAAA-MM-JJ attendu).', $col);
            }
        }
        if ('' === $this->slug($v['prenom'])) {
            return 'prénom inutilisable comme identifiant.';
        }
        if (null === $this->aptitudes->findOneBy(['code' => $v['aptitude']])) {
            return sprintf('aptitude « %s » inconnue en base.', $v['aptitude']);
        }

        return null;
    }

    private function date(string $valeur): ?\DateTimeImmutable
    {
        if ('' === $valeur) {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $valeur);

        return false !== $date && $date->format('Y-m-d') === $valeur ? $date : null;
    }

    private function slug(string $texte): string
    {
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $texte) ?: '';
        $slug = preg_replace('/[^a-z0-9]+/', '-', $ascii);

        return trim((string) $slug, '-');
    }
}
