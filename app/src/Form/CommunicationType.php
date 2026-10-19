<?php

namespace App\Form;

use App\Entity\Communication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommunicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'help' => 'Objet du mail, précédé de « CN95 Plongée – ».',
                'attr' => ['maxlength' => 150],
            ])
            // Éditeur riche (Stimulus « wysiwyg ») : le HTML est nettoyé par le contrôleur avant enregistrement
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu',
                'required' => false,
                'attr' => ['rows' => 14, 'data-controller' => 'wysiwyg', 'class' => 'wysiwyg-pleine-largeur'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Communication::class]);
    }
}
