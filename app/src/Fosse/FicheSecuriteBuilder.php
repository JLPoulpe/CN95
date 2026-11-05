<?php

namespace App\Fosse;

use App\Entity\FicheSecurite;
use App\Entity\InvitationFosse;
use App\Entity\Palanquee;
use App\Entity\Role;
use App\Entity\User;
use App\Enum\StatutReponseFosse;

/** Génère et tient à jour la fiche de sécurité d'une invitation à partir des inscrits de la liste principale. */
class FicheSecuriteBuilder
{
    /** Adhérents accompagnant le chef dans une palanquée. */
    private const ACCOMPAGNANTS = Palanquee::TAILLE - 1;

    /** @return list<User> Inscrits de la liste principale, par ordre alphabétique */
    public function inscrits(InvitationFosse $invitation): array
    {
        $users = [];
        foreach ($invitation->getReponses() as $reponse) {
            if (StatutReponseFosse::Inscrit === $reponse->getStatut()) {
                $users[] = $reponse->getUser();
            }
        }
        usort($users, fn (User $a, User $b) => [mb_strtoupper((string) $a->getNom()), $a->getPrenom()] <=> [mb_strtoupper((string) $b->getNom()), $b->getPrenom()]);

        return $users;
    }

    /**
     * Crée la fiche (non enregistrée) : une palanquée par moniteur inscrit, dans la limite de ceil(inscrits / 5),
     * chacune complétée avec les adhérents inscrits des groupes (aptitudes encadrées) du moniteur, 4 au plus.
     */
    public function generer(InvitationFosse $invitation): FicheSecurite
    {
        $fiche = new FicheSecurite($invitation);
        $inscrits = $this->inscrits($invitation);

        $moniteurs = array_values(array_filter($inscrits, fn (User $u) => $u->hasRole(Role::MONITEUR)));
        $adherents = array_values(array_filter($inscrits, fn (User $u) => !$u->hasRole(Role::MONITEUR)));

        // Le DP passe en premier : il a toujours une palanquée
        $dp = $invitation->getDp();
        usort($moniteurs, fn (User $a, User $b) => [$a === $dp ? 0 : 1] <=> [$b === $dp ? 0 : 1]);

        $nombre = min(count($moniteurs), (int) ceil(count($inscrits) / Palanquee::TAILLE));
        foreach (array_slice($moniteurs, 0, $nombre) as $moniteur) {
            $palanquee = $fiche->ajouterPalanquee()->ajouter($moniteur);

            $groupes = array_map(fn ($aptitude) => $aptitude->getCode(), $moniteur->getAptitudesEncadrees()->toArray());
            foreach ($adherents as $adherent) {
                if ($palanquee->getMembres()->count() > self::ACCOMPAGNANTS) {
                    break;
                }
                if (!$fiche->estPlace($adherent) && null !== $adherent->getAptitudePreparee() && in_array($adherent->getAptitudePreparee()->getCode(), $groupes, true)) {
                    $palanquee->ajouter($adherent);
                }
            }
        }

        return $fiche;
    }

    /** Retire des palanquées les personnes qui ne sont plus inscrites en liste principale (désistement, passage en attente). */
    public function synchroniser(FicheSecurite $fiche): void
    {
        $inscrits = $this->inscrits($fiche->getInvitation());
        foreach ($fiche->getPalanquees() as $palanquee) {
            foreach ($palanquee->getMembres()->toArray() as $membre) {
                if (!in_array($membre->getUser(), $inscrits, true)) {
                    $palanquee->retirer($membre->getUser());
                }
            }
        }
    }

    /** @return array{moniteurs: list<User>, adherents: list<User>} Inscrits pas encore placés, par rôle */
    public function restants(FicheSecurite $fiche): array
    {
        $restants = ['moniteurs' => [], 'adherents' => []];
        foreach ($this->inscrits($fiche->getInvitation()) as $user) {
            if (!$fiche->estPlace($user)) {
                $restants[$user->hasRole(Role::MONITEUR) ? 'moniteurs' : 'adherents'][] = $user;
            }
        }

        return $restants;
    }
}
