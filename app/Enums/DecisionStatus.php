<?php

namespace App\Enums;

enum DecisionStatus: string
{
    case Draft = 'draft';                               // the Secretariat is preparing it
    case AwaitingConfirmation = 'awaiting_confirmation'; // with the Chairperson
    case Released = 'released';                         // confirmed; visible to the researcher

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::AwaitingConfirmation => 'Awaiting Chair confirmation',
            self::Released => 'Released',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'text-bg-secondary',
            self::AwaitingConfirmation => 'text-bg-info',
            self::Released => 'text-bg-success',
        };
    }
}
