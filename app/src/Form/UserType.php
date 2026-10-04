<?php

namespace App\Form;

use App\Entity\Aptitude;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\AptitudeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Count;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['data']?->getId() !== null;
        $ordered = fn (AptitudeRepository $r) => $r->createQueryBuilder('a')->orderBy('a.ordre');

        $builder
            ->add('nom')
            ->add('prenom', null, ['label' => 'Prénom'])
            ->add('email', EmailType::class)
            ->add('telephone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('aptitude', EntityType::class, [
                'class' => Aptitude::class,
                'query_builder' => $ordered,
                'placeholder' => 'Choisir…',
            ])
            ->add('aptitudePreparee', EntityType::class, [
                'class' => Aptitude::class,
                'query_builder' => $ordered,
                'label' => 'Aptitude préparée',
                'required' => false,
                'placeholder' => 'Aucune',
            ])
            ->add('username', null, ['label' => 'Identifiant'])
            ->add('plainPassword', PasswordType::class, [
                'label' => $isEdit ? 'Nouveau mot de passe (laisser vide pour ne pas changer)' : 'Mot de passe',
                'mapped' => false,
                'required' => !$isEdit,
                'constraints' => $isEdit ? [new Length(min: 8)] : [new NotBlank(), new Length(min: 8)],
            ])
            ->add('userRoles', EntityType::class, [
                'class' => Role::class,
                'label' => 'Rôles',
                'multiple' => true,
                'expanded' => true,
                'by_reference' => false,
                'attr' => ['class' => 'choices-inline'],
                'constraints' => [new Count(min: 1, minMessage: 'Choisir au moins un rôle.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
