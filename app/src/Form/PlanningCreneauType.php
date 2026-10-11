<?php

namespace App\Form;

use App\Entity\Activite;
use App\Entity\Aptitude;
use App\Entity\LigneEau;
use App\Entity\PlanningCreneau;
use App\Enum\Jour;
use App\Enum\Lieu;
use App\Repository\ActiviteRepository;
use App\Repository\AptitudeRepository;
use App\Repository\LigneEauRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanningCreneauType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('jour', EnumType::class, ['class' => Jour::class, 'choice_label' => fn (Jour $j) => $j->label()])
            ->add('lieu', EnumType::class, ['class' => Lieu::class, 'choice_label' => fn (Lieu $l) => $l->label()])
            ->add('lignes', EntityType::class, [
                'class' => LigneEau::class,
                'label' => "Lignes d'eau utilisées",
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'choice_label' => fn (LigneEau $l) => $l->getLabel(),
                'attr' => ['class' => 'choices-inline'],
                'query_builder' => fn (LigneEauRepository $r) => $r->createQueryBuilder('l')->orderBy('l.lieu')->addOrderBy('l.numero'),
            ])
            ->add('activites', EntityType::class, [
                'class' => Activite::class,
                'label' => 'Activités',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'choice_label' => fn (Activite $a) => $a->getLibelle(),
                'attr' => ['class' => 'choices-inline'],
                'query_builder' => fn (ActiviteRepository $r) => $r->createQueryBuilder('a')->orderBy('a.ordre'),
            ])
            ->add('libelle', null, ['label' => 'Précision (ex. Init R, CODIR)', 'required' => false])
            ->add('aptitudes', EntityType::class, [
                'class' => Aptitude::class,
                'label' => 'Aptitudes (aucune = tous niveaux)',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'by_reference' => false,
                'attr' => ['class' => 'choices-inline'],
                'query_builder' => fn (AptitudeRepository $r) => $r->createQueryBuilder('a')->orderBy('a.ordre'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PlanningCreneau::class]);
    }
}
