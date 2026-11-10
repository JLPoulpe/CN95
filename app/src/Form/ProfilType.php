<?php

namespace App\Form;

use App\Entity\Aptitude;
use App\Entity\User;
use App\Repository\AptitudeRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Données personnelles modifiables par le membre lui-même. Identifiant et rôles en sont volontairement absents :
 * ils restent réservés à la gestion des utilisateurs (sinon un adhérent pourrait s'attribuer des droits).
 */
class ProfilType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $ordered = fn (AptitudeRepository $r) => $r->createQueryBuilder('a')->orderBy('a.ordre');

        $builder
            ->add('nom')
            ->add('prenom', null, ['label' => 'Prénom'])
            ->add('email', EmailType::class)
            ->add('telephone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('dateNaissance', DateType::class, ['label' => 'Date de naissance', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('caciExpireLe', DateType::class, ['label' => 'Expiration du CACI', 'required' => false, 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('aptitude', EntityType::class, [
                'class' => Aptitude::class,
                'query_builder' => $ordered,
                'label' => 'Niveau (aptitude)',
                'placeholder' => 'Choisir…',
            ])
            ->add('aptitudePreparee', EntityType::class, [
                'class' => Aptitude::class,
                'query_builder' => $ordered,
                'label' => 'Niveau préparé',
                'required' => false,
                'placeholder' => 'Aucun',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
