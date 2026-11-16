<?php

namespace App\Security\Voter;

use App\Entity\PlanningCreneau;
use App\Entity\User;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Modification d'un créneau du planning : libre avec la permission « Configuration des activités » ;
 * sinon, avec la permission « Planning de ses groupes », uniquement si au moins une aptitude du créneau
 * fait partie des groupes que l'utilisateur encadre.
 * Avec la permission « Gestion des salles », les groupes de tout créneau en salle sont modifiables (CHANGER_GROUPE),
 * sans pouvoir supprimer le créneau.
 *
 * @extends Voter<string, PlanningCreneau>
 */
class PlanningCreneauVoter extends Voter
{
    public const GERER = 'PLANNING_CRENEAU_GERER';
    /** Changer les groupes (aptitudes) d'un créneau : GERER, ou gestionnaire de salle sur un créneau en salle. */
    public const CHANGER_GROUPE = 'PLANNING_CRENEAU_CHANGER_GROUPE';

    public function __construct(private readonly AuthorizationCheckerInterface $checker)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::GERER, self::CHANGER_GROUPE], true) && $subject instanceof PlanningCreneau;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($this->checker->isGranted(Permission::CONFIG_ACTIVITES)) {
            return true;
        }
        if (self::CHANGER_GROUPE === $attribute && $subject->estEnSalle() && $this->checker->isGranted(Permission::GESTION_SALLES)) {
            return true;
        }

        return $this->checker->isGranted(Permission::PLANNING_GROUPES) && $user->encadreUneAptitudeDe($subject);
    }
}
