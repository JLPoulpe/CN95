<?php

namespace App\Form;

use App\Entity\LigneEau;
use App\Enum\Lieu;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LigneEauType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('lieu', EnumType::class, [
                'class' => Lieu::class,
                'choices' => array_filter(Lieu::cases(), fn (Lieu $l) => $l->aDesLignes()),
                'choice_label' => fn (Lieu $l) => $l->label(),
            ])
            ->add('numero', IntegerType::class, ['label' => 'Numéro de ligne']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LigneEau::class]);
    }
}
