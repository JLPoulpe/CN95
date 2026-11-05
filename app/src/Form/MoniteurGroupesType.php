<?php

namespace App\Form;

use App\Entity\Aptitude;
use App\Entity\User;
use App\Repository\AptitudeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Aptitudes préparées dont un moniteur a la charge (choix multiple en pastilles). */
class MoniteurGroupesType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('aptitudesEncadrees', EntityType::class, [
            'class' => Aptitude::class,
            'query_builder' => fn (AptitudeRepository $r) => $r->createQueryBuilder('a')->orderBy('a.ordre'),
            'label' => 'Aptitudes à préparer',
            'multiple' => true,
            'expanded' => true,
            'required' => false,
            'by_reference' => false,
            'attr' => ['class' => 'choices-inline'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
