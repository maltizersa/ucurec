<?php

namespace App\Enums;

enum Role: string
{
    case Researcher = 'researcher';
    case Secretariat = 'secretariat';
    case Reviewer = 'reviewer';
    case Chair = 'chair';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Researcher => 'Researcher',
            self::Secretariat => 'REC Secretariat',
            self::Reviewer => 'Reviewer',
            self::Chair => 'REC Chairperson',
            self::Admin => 'IT Administrator',
        };
    }

    /**
     * Roles that staff accounts can have (created by the IT administrator).
     *
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::Secretariat, self::Reviewer, self::Chair, self::Admin];
    }

    /**
     * @return array<string, string> value => label, for select inputs
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $role) {
            $options[$role->value] = $role->label();
        }

        return $options;
    }
}
