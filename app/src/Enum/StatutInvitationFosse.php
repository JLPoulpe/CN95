<?php

namespace App\Enum;

enum StatutInvitationFosse: string
{
    case Active = 'ACTIVE';
    case Annulee = 'ANNULEE';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Annulee => 'Annulée',
        };
    }
}
