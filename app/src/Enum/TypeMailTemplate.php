<?php

namespace App\Enum;

enum TypeMailTemplate: string
{
    case Invitation = 'INVITATION';
    case Annulation = 'ANNULATION';
    case DesistementResponsable = 'DESISTEMENT_RESPONSABLE';
    case ActivationCompte = 'ACTIVATION_COMPTE';

    public function label(): string
    {
        return match ($this) {
            self::Invitation => 'Invitation',
            self::Annulation => 'Annulation',
            self::DesistementResponsable => 'Désistement (responsable)',
            self::ActivationCompte => 'Activation de compte',
        };
    }
}
