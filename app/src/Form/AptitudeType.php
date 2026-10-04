<?php

namespace App\Form;

use App\Entity\Aptitude;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AptitudeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code')
            ->add('libelle', null, ['label' => 'Libellé', 'required' => false])
            ->add('ordre', IntegerType::class, ['label' => 'Ordre d’affichage']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Aptitude::class]);
    }
}
