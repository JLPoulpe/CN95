<?php

namespace App\Form;

use App\Entity\Aptitude;
use App\Entity\Role;
use App\Repository\AptitudeRepository;
use App\Repository\RoleRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

/** Filtres des destinataires d'une communication. Sans rien cocher, tous les membres actifs sont concernés. */
class DestinatairesCommunicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('roles', EntityType::class, [
                'class' => Role::class,
                'label' => 'Rôles',
                'help' => 'Laisser vide pour ne pas filtrer par rôle.',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'choice_label' => 'libelle',
                'query_builder' => fn (RoleRepository $r) => $r->createQueryBuilder('r')->orderBy('r.id'),
                'attr' => ['class' => 'choices-inline'],
            ])
            ->add('aptitudes', EntityType::class, [
                'class' => Aptitude::class,
                'label' => 'Aptitudes',
                'help' => 'Aptitude principale du membre. Laisser vide pour ne pas filtrer par aptitude.',
                'multiple' => true,
                'expanded' => true,
                'required' => false,
                'query_builder' => fn (AptitudeRepository $r) => $r->createQueryBuilder('a')->orderBy('a.ordre'),
                'attr' => ['class' => 'choices-inline'],
            ]);
    }
}
