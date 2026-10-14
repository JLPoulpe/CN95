<?php

namespace App\Controller;

use App\Entity\Role;
use App\Entity\User;
use App\Planning\VuePlanning;
use App\Repository\AptitudeRepository;
use App\Repository\PlanningSemaineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PlanningController extends AbstractController
{
    /** Nombre maximal de semaines consultables à l'avance. */
    private const HORIZON_SEMAINES = 52;

    #[Route('/planning/{date}', name: 'app_planning', requirements: ['date' => '\\d{4}-\\d{2}-\\d{2}'], defaults: ['date' => null])]
    public function index(?string $date, PlanningSemaineRepository $semaines, AptitudeRepository $aptitudes, VuePlanning $vue): Response
    {
        $courante = self::lundiDe(new \DateTimeImmutable('today'));
        $lundi = $courante;
        if (null !== $date) {
            $demande = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if (false === $demande) {
                throw $this->createNotFoundException();
            }
            $lundi = self::lundiDe($demande);
        }

        $limite = $courante->modify('+'.self::HORIZON_SEMAINES.' weeks');
        if ($lundi < $courante || $lundi > $limite) {
            return $this->redirectToRoute('app_planning', ['date' => ($lundi < $courante ? $courante : $limite)->format('Y-m-d')]);
        }

        $semaine = $semaines->findByLundi($lundi);

        $jours = $vue->jours($semaine, $lundi);

        return $this->render('planning/index.html.twig', [
            'lundi' => $lundi,
            'semaine' => $semaine,
            'jours' => $jours,
            'aptitudes' => $aptitudes->findBy([], ['ordre' => 'ASC']),
            'precedente' => $lundi > $courante ? $lundi->modify('-1 week') : null,
            'suivante' => $lundi < $limite ? $lundi->modify('+1 week') : null,
            'estCourante' => $lundi == $courante,
            'aptitudeDefaut' => $this->aptitudeDefaut(),
        ]);
    }

    /**
     * Pour un adhérent dont c'est le seul rôle, le planning s'ouvre filtré sur son aptitude (le filtre reste désactivable).
     */
    private function aptitudeDefaut(): ?string
    {
        $utilisateur = $this->getUser();
        if (!$utilisateur instanceof User) {
            return null;
        }

        $codesRoles = array_map(fn (Role $role) => $role->getCode(), $utilisateur->getUserRoles()->toArray());
        if ([Role::ADHERENT] !== $codesRoles) {
            return null;
        }

        return $utilisateur->getAptitude()?->getCode();
    }

    public static function lundiDe(\DateTimeImmutable $d): \DateTimeImmutable
    {
        return $d->setTime(0, 0)->modify('-'.((int) $d->format('N') - 1).' days');
    }
}
