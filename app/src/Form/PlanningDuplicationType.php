<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class PlanningDuplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('lundi', DateType::class, [
            'label' => 'Lundi de la semaine à créer',
            'widget' => 'single_text',
            'input' => 'datetime_immutable',
            'constraints' => [
                new NotNull(),
                new Callback(function (?\DateTimeImmutable $d, ExecutionContextInterface $c) {
                    if ($d && '1' !== $d->format('N')) {
                        $c->buildViolation('La date doit être un lundi.')->addViolation();
                    }
                }),
            ],
        ]);
    }
}
