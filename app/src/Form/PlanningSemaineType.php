<?php

namespace App\Form;

use App\Entity\PlanningSemaine;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PlanningSemaineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lundi', DateType::class, [
                'label' => 'Lundi de la semaine',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('dp', EntityType::class, [
                'class' => User::class,
                'label' => 'DP',
                'required' => false,
                'placeholder' => 'Aucun',
                'query_builder' => fn (UserRepository $r) => $r->createQueryBuilder('u')
                    ->join('u.userRoles', 'r')->where('r.code = :code')->setParameter('code', Role::MONITEUR)
                    ->orderBy('u.nom')->addOrderBy('u.prenom'),
            ])
            ->add('fermee', CheckboxType::class, [
                'label' => 'Semaine fermée (pas d\'entraînement)',
                'required' => false,
                'row_attr' => ['class' => 'check-row'],
                'attr' => ['data-semaine-fermee-target' => 'case', 'data-action' => 'semaine-fermee#toggle'],
            ])
            ->add('motif', null, [
                'label' => 'Motif',
                'required' => false,
                'row_attr' => ['data-semaine-fermee-target' => 'motif'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PlanningSemaine::class, 'attr' => ['data-controller' => 'semaine-fermee']]);
    }
}
