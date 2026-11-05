<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\Aptitude;
use App\Entity\LieuActivite;
use App\Entity\PlanningCreneau;
use App\Enum\Jour;
use App\Repository\ActiviteRepository;
use App\Repository\AptitudeRepository;
use App\Repository\LieuActiviteRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;

class PlanningCreneauType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Moniteur limité à ses groupes : seules ses aptitudes sont proposées, et au moins une est exigée
        $limitees = $options['aptitudes_autorisees'];
        // Moniteur : seule la présence de ses groupes est modifiable, le reste du créneau est en lecture seule
        $lecture = null === $limitees ? [] : ['disabled' => true];

        $builder
            ->add('jour', EnumType::class, ['class' => Jour::class, 'choice_label' => fn (Jour $j) => $j->label()] + $lecture)
            ->add('lieux', EntityType::class, [
                'class' => LieuActivite::class,
                'label' => 'Lieux',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                // Le groupe est affiché en petit au-dessus du nom
                'label_html' => true,
                'choice_label' => fn (LieuActivite $l) => '<span><small class="chip-groupe">'.htmlspecialchars($l->getGroupe()).'</small>'.htmlspecialchars($l->getNom()).'</span>',
                'attr' => ['class' => 'choices-inline'],
                'query_builder' => fn (LieuActiviteRepository $r) => $r->createQueryBuilder('l')->orderBy('l.ordre')->addOrderBy('l.groupe')->addOrderBy('l.nom'),
            ] + $lecture)
            ->add('activites', EntityType::class, [
                'class' => Activite::class,
                'label' => 'Activités',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'choice_label' => fn (Activite $a) => $a->getLibelle(),
                'attr' => ['class' => 'choices-inline'],
                'query_builder' => fn (ActiviteRepository $r) => $r->createQueryBuilder('a')->orderBy('a.ordre'),
            ] + $lecture)
            ->add('libelle', null, ['label' => 'Précision (ex. Init R, CODIR)', 'required' => false] + $lecture)
            ->add('aptitudes', EntityType::class, [
                'class' => Aptitude::class,
                'label' => null === $limitees ? 'Aptitudes (aucune = tous niveaux)' : 'Aptitudes',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'attr' => ['class' => 'choices-inline'],
                // Aptitudes visibles, plus celles déjà portées par ce créneau (pour ne pas les perdre à l'enregistrement)
                'query_builder' => static function (AptitudeRepository $r) use ($options) {
                    $qb = $r->createQueryBuilder('a')->orderBy('a.ordre');
                    $deja = $options['data'] instanceof PlanningCreneau ? $options['data']->getAptitudes()->toArray() : [];
                    if ($deja) {
                        $qb->where('a.visible = true OR a IN (:deja)')->setParameter('deja', $deja);
                    } else {
                        $qb->where('a.visible = true');
                    }

                    return $qb;
                },
            ] + (null === $limitees
                ? []
                : [
                    // Les aptitudes des autres groupes sont affichées mais non modifiables
                    'choice_attr' => static fn (Aptitude $a) => self::estDans($a, $limitees) ? [] : ['disabled' => 'disabled', 'class' => 'hors-groupe'],
                    'constraints' => [new Count(min: 1, minMessage: 'Choisir au moins une de vos aptitudes.')],
                ]));

        if (null !== $limitees) {
            // Un moniteur ne peut soumettre que ses propres aptitudes ; les autres restent inchangées (cf. contrôleur)
            $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($limitees): void {
                $data = $event->getData();
                if (!is_array($data) || !isset($data['aptitudes'])) {
                    return;
                }
                $ids = array_map(static fn (Aptitude $a) => (string) $a->getId(), $limitees);
                $data['aptitudes'] = array_values(array_filter(
                    (array) $data['aptitudes'],
                    static fn ($id) => in_array((string) $id, $ids, true),
                ));
                $event->setData($data);
            });
        }
    }

    /** @param list<Aptitude> $limitees */
    private static function estDans(Aptitude $aptitude, array $limitees): bool
    {
        foreach ($limitees as $l) {
            if ($l->getId() === $aptitude->getId()) {
                return true;
            }
        }

        return false;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PlanningCreneau::class, 'aptitudes_autorisees' => null]);
        $resolver->setAllowedTypes('aptitudes_autorisees', ['null', 'array']);
    }
}
