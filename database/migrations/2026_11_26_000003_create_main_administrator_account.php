<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the main administrator account (settings in config/ucurec.php).
 *
 * Uses the query builder instead of the User model so this migration keeps
 * working when the model changes later.
 */
return new class extends Migration
{
    public function up(): void
    {
        $settings = config('ucurec.main_admin');
        $email = Str::lower(trim((string) $settings['email']));

        if ($email === '') {
            return;
        }

        $now = now();
        $existing = DB::table('users')->where('email', $email)->first();

        if ($existing) {
            // Promote an existing account without touching its password.
            DB::table('users')->where('id', $existing->id)->update([
                'role' => 'admin',
                'is_main_admin' => true,
                'is_active' => true,
                'activated_at' => $existing->activated_at ?? $now,
                'deactivated_at' => null,
                'deactivated_by' => null,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('users')->insert([
            'name' => $settings['name'],
            'email' => $email,
            'role' => 'admin',
            'is_main_admin' => true,
            'is_active' => true,
            'activated_at' => $now,
            'password' => Hash::make($settings['password']),
            'must_change_password' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Accounts are never deleted automatically; rolling back only removes the
     * main-administrator mark (the column itself is dropped by the previous
     * migration's rollback).
     */
    public function down(): void
    {
        DB::table('users')->where('is_main_admin', true)->update(['is_main_admin' => false]);
    }
};
