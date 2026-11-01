<?php

namespace App\Models;

use App\Enums\AdminRole;
use App\Enums\StaffRole;
use App\Services\Messaging\PhoneNumber;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'admin_role' => AdminRole::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if ($user->isDirty('phone')) {
                $user->phone_e164 = static::normalizePhone($user->phone);
            }

            $user->email = filled($user->email) ? strtolower(trim($user->email)) : null;
        });
    }

    /** International digits (919876543210), or null when there is no number. */
    public static function normalizePhone(?string $phone): ?string
    {
        return filled(preg_replace('/\D/', '', (string) $phone)) ? PhoneNumber::international((string) $phone) : null;
    }

    /** The login for this mobile number, preferring one that has a member profile. */
    public static function findByPhone(?string $phone): ?self
    {
        $normalized = static::normalizePhone($phone);

        return $normalized
            ? static::query()->where('phone_e164', $normalized)->withExists('memberProfiles')->orderByDesc('member_profiles_exists')->orderBy('id')->first()
            : null;
    }

    /** Gyms this user works at (as owner, manager, trainer or receptionist). */
    public function gyms(): BelongsToMany
    {
        return $this->belongsToMany(Gym::class)
            ->using(GymUser::class)
            ->withPivot(['id', 'role', 'branch_id', 'is_active'])
            ->withTimestamps();
    }

    /** Platform admin role; older super admins without a role count as owners. */
    public function adminRole(): ?AdminRole
    {
        return $this->admin_role ?? ($this->is_super_admin ? AdminRole::Owner : null);
    }

    public function isPlatformAdmin(): bool
    {
        return $this->adminRole() !== null;
    }

    public function partner(): HasOne
    {
        return $this->hasOne(Partner::class);
    }

    /** Member profiles linked to this login (one per gym the person trains at). */
    public function memberProfiles(): HasMany
    {
        return $this->hasMany(Member::class)->withoutGlobalScopes();
    }

    public function roleIn(Gym $gym): ?StaffRole
    {
        // The GymUser pivot casts role to StaffRole.
        return $this->gyms()->whereKey($gym->getKey())->wherePivot('is_active', true)->first()?->pivot->role;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'admin' => $this->isPlatformAdmin(),
            // Anyone who is not purely a gym member may open the gym panel; a new
            // owner without a gym yet is sent to the "register your gym" wizard.
            'gym' => $this->gyms()->wherePivot('is_active', true)->exists() || ! $this->memberProfiles()->exists(),
            'member' => $this->memberProfiles()->exists(),
            'partner' => $this->partner()->where('is_active', true)->exists(),
            default => false,
        };
    }

    public function getTenants(Panel $panel): Collection
    {
        // The super admin can switch into any gym to help it.
        return $this->is_super_admin
            ? Gym::query()->orderBy('name')->get()
            : $this->gyms()->wherePivot('is_active', true)->get();
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->is_super_admin
            || $this->gyms()->whereKey($tenant->getKey())->wherePivot('is_active', true)->exists();
    }
}
