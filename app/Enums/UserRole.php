<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Citizen = 'citizen';
    case Officer = 'officer';
    case Mayor = 'mayor';
    case Admin = 'admin';

    /** Les roles officiels ne peuvent jamais s'auto-inscrire (4.1 du brief). */
    public function isOfficial(): bool
    {
        return $this !== self::Citizen;
    }

    /** 2FA obligatoire pour officier, maire et administrateur. */
    public function requiresTwoFactor(): bool
    {
        return $this->isOfficial();
    }

    public function label(): string
    {
        return match ($this) {
            self::Citizen => 'Citoyen',
            self::Officer => "Officier d'état civil",
            self::Mayor => 'Maire',
            self::Admin => 'Administrateur',
        };
    }
}
