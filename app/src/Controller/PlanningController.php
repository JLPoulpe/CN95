<?php

namespace App\Controller;

use App\Enum\Jour;
use App\Enum\Lieu;
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
    public function index(?string $date, PlanningSemaineRepository $semaines, AptitudeRepository $aptitudes): Response
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

        // Regroupement jour > lieu, dans l'ordre des enums
        $jours = [];
        if ($semaine && !$semaine->isFermee()) {
            foreach (Jour::cases() as $jour) {
                foreach (Lieu::cases() as $lieu) {
                    $liste = $semaine->getCreneaux()->filter(fn ($c) => $c->getJour() === $jour && $c->getLieu() === $lieu)->getValues();
                    if ($liste) {
                        $jours[$jour->value]['lieux'][$lieu->value] = ['lieu' => $lieu, 'creneaux' => $liste];
                    }
                }
                if (isset($jours[$jour->value])) {
                    $jours[$jour->value]['jour'] = $jour;
                    $jours[$jour->value]['date'] = $lundi->modify('+'.$jour->decalage().' days');
                }
            }
        }

        return $this->render('planning/index.html.twig', [
            'lundi' => $lundi,
            'semaine' => $semaine,
            'jours' => $jours,
            'aptitudes' => $aptitudes->findBy([], ['ordre' => 'ASC']),
            'precedente' => $lundi > $courante ? $lundi->modify('-1 week') : null,
            'suivante' => $lundi < $limite ? $lundi->modify('+1 week') : null,
            'estCourante' => $lundi == $courante,
        ]);
    }

    public static function lundiDe(\DateTimeImmutable $d): \DateTimeImmutable
    {
        return $d->setTime(0, 0)->modify('-'.((int) $d->format('N') - 1).' days');
    }
}
