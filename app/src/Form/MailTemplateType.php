<?php

namespace App\Form;

use App\Entity\MailTemplate;
use App\Enum\TypeMailTemplate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class MailTemplateType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom',
                'constraints' => [new NotBlank(message: 'Donner un nom au modèle.'), new Length(max: 100)],
            ])
            ->add('type', EnumType::class, [
                'class' => TypeMailTemplate::class,
                'label' => 'Utilisé pour',
                'choice_label' => fn (TypeMailTemplate $type) => $type->label(),
            ])
            // Transformé en éditeur riche par le contrôleur Stimulus « wysiwyg » ; le HTML reste dans ce champ
            ->add('contenu', TextareaType::class, [
                'label' => 'Contenu',
                'attr' => ['rows' => 14, 'data-controller' => 'wysiwyg'],
                'constraints' => [new NotBlank(message: 'Le contenu ne peut pas être vide.')],
            ])
            ->add('parDefaut', CheckboxType::class, [
                'label' => 'Modèle par défaut pour ce type',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MailTemplate::class]);
    }
}
