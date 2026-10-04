<?php

namespace App\Form;

use App\Entity\Aptitude;
use App\Entity\PlanningCreneau;
use App\Enum\Activite;
use App\Enum\Jour;
use App\Enum\Lieu;
use App\Repository\AptitudeRepository;
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
            ->add('lignes', null, ['label' => 'Lignes d\'eau (ex. 6/5)', 'required' => false])
            ->add('activites', EnumType::class, [
                'class' => Activite::class,
                'label' => 'Activités',
                'multiple' => true,
                'expanded' => true,
                'choice_label' => fn (Activite $a) => $a->label(),
                'attr' => ['class' => 'choices-inline'],
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
