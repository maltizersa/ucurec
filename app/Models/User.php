<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Status fields (is_active, activated_by, ...) are deliberately not mass
     * assignable; they change only through activate() / deactivate().
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'role',
        'college_program',
        'contact_number',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => Role::class,
            'is_main_admin' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'activated_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * The highest account, created by migration. Only it can manage other
     * administrator accounts, and no one else can change it.
     */
    public function isMainAdmin(): bool
    {
        return $this->isAdmin() && $this->is_main_admin;
    }

    /**
     * An account that has never been activated (e.g. a new researcher sign-up).
     */
    public function isPendingActivation(): bool
    {
        return ! $this->is_active && $this->activated_at === null;
    }

    public function activate(User $by): void
    {
        $this->forceFill([
            'is_active' => true,
            'activated_at' => now(),
            'activated_by' => $by->id,
            'deactivated_at' => null,
            'deactivated_by' => null,
        ])->save();
    }

    public function deactivate(User $by): void
    {
        $this->forceFill([
            'is_active' => false,
            'deactivated_at' => now(),
            'deactivated_by' => $by->id,
        ])->save();
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    public function scopeRole(Builder $query, Role $role): void
    {
        $query->where('role', $role->value);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopePendingActivation(Builder $query): void
    {
        $query->where('is_active', false)->whereNull('activated_at');
    }
}
