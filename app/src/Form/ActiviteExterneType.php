<?php

namespace App\Form;

use App\Entity\ActiviteExterne;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ActiviteExterneType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code')
            ->add('libelle', null, ['label' => 'Libellé'])
            ->add('necessiteResponsable', CheckboxType::class, [
                'label' => 'Nécessite un responsable (DP)',
                'required' => false,
                'help' => 'Si décoché, aucun responsable ne sera demandé lors de la création d\'une invitation pour cette activité.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ActiviteExterne::class]);
    }
}
