<?php

namespace App\Form;

use App\Entity\LieuActivite;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LieuActiviteType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, ['label' => 'Nom (ex. Ligne 1, Grande salle)'])
            ->add('groupe', TextType::class, [
                'label' => 'Groupe (ex. Petit bassin, Salles)',
                'attr' => ['list' => 'groupes-lieux', 'autocomplete' => 'off'],
            ])
            ->add('ordre', IntegerType::class, ['label' => "Ordre d'affichage"]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LieuActivite::class]);
    }
}
