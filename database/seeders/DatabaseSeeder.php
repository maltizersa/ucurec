<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * FICTIONAL demo accounts for development and the pilot demo only.
 * Never run this seeder on the live server.
 *
 * Every demo account uses the password below.
 */
class DatabaseSeeder extends Seeder
{
    public const DEMO_PASSWORD = 'Ucurec2026';

    public function run(): void
    {
        // Demo accounts share one known password: never on the live server.
        if (app()->isProduction()) {
            $this->command?->error('Refused: demo accounts are never seeded in production.');

            return;
        }

        $accounts = [
            ['IT Administrator', 'admin@ucurec.test', Role::Admin, null],
            ['Maria Santos', 'secretariat@ucurec.test', Role::Secretariat, null],
            ['Dr. Jose Ramos', 'chair@ucurec.test', Role::Chair, null],
            ['Dr. Ana Villanueva', 'reviewer1@ucurec.test', Role::Reviewer, 'College of Nursing'],
            ['Prof. Ramon Dela Cruz', 'reviewer2@ucurec.test', Role::Reviewer, 'College of Education'],
            ['Dr. Liza Fernandez', 'reviewer3@ucurec.test', Role::Reviewer, 'Graduate School'],
            ['Juan Reyes', 'researcher1@ucurec.test', Role::Researcher, 'College of Computer Studies'],
            ['Carla Mendoza', 'researcher2@ucurec.test', Role::Researcher, 'College of Nursing'],
        ];

        foreach ($accounts as [$name, $email, $role, $college]) {
            User::factory()->role($role)->create([
                'name' => $name,
                'email' => $email,
                'college_program' => $college,
                'password' => self::DEMO_PASSWORD,
            ]);
        }

        // A researcher sign-up the Secretariat has not activated yet.
        User::factory()->pending()->create([
            'name' => 'Paolo Garcia',
            'email' => 'pending.researcher@ucurec.test',
            'college_program' => 'College of Arts and Sciences',
            'password' => self::DEMO_PASSWORD,
        ]);
    }
}
