<?php

namespace App\Form;

use App\Entity\ActiviteExterne;
use App\Entity\Aptitude;
use App\Entity\InvitationFosse;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\AptitudeRepository;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;

class InvitationFosseType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // editionLimitee : une fois l'invitation envoyée, seuls le responsable et le commentaire restent modifiables
        if (!$options['editionLimitee']) {
            $builder
                ->add('date', DateType::class, [
                    'label' => 'Date',
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'constraints' => [new GreaterThanOrEqual('today', message: 'La date doit être aujourd\'hui ou plus tard.')],
                ])
                ->add('aptitudes', EntityType::class, [
                    'class' => Aptitude::class,
                    'label' => 'Aptitudes conviées',
                    'multiple' => true,
                    'expanded' => true,
                    'by_reference' => false,
                    'query_builder' => fn (AptitudeRepository $r) => $r->createQueryBuilder('a')->orderBy('a.ordre'),
                    'attr' => ['class' => 'choices-inline'],
                ]);
        }

        /** @var ActiviteExterne|null $activiteExterne */
        $activiteExterne = $options['activiteExterne'];
        if (null === $activiteExterne || $activiteExterne->isNecessiteResponsable()) {
            $builder->add('dp', EntityType::class, [
                'class' => User::class,
                'label' => 'Responsable',
                'required' => false,
                'placeholder' => 'Choisir…',
                'query_builder' => fn (UserRepository $r) => $r->createQueryBuilder('u')
                    ->join('u.userRoles', 'r')->where('r.code = :code')->setParameter('code', Role::MONITEUR)
                    ->orderBy('u.nom')->addOrderBy('u.prenom'),
            ]);
        }

        $builder->add('commentaire', TextareaType::class, [
            'label' => 'Commentaire',
            'required' => false,
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => InvitationFosse::class, 'editionLimitee' => false, 'activiteExterne' => null]);
        $resolver->setAllowedTypes('editionLimitee', 'bool');
        $resolver->setAllowedTypes('activiteExterne', [ActiviteExterne::class, 'null']);
    }
}
