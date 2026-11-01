<?php

namespace App\Models;

use App\Support\Country;
use Filament\Models\Contracts\HasAvatar;
use Filament\Models\Contracts\HasCurrentTenantLabel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Gym extends Model implements HasAvatar, HasCurrentTenantLabel
{
    use HasFactory, SoftDeletes;

    public const DAYS = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'trial',
        'country' => 'IN',
        'primary_color' => '#f59e0b',
        'currency' => 'INR',
        'timezone' => 'Asia/Kolkata',
        'invoice_prefix' => 'INV',
        'member_code_prefix' => 'M',
        'onboarding_completed' => false,
    ];

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'settings' => 'array',
            'integrations' => 'encrypted:array',
            'website' => 'array',
            'custom_domain_verified_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'message_credits' => 'integer',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'onboarding_completed' => 'boolean',
            'gst_registered' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Gym $gym): void {
            $gym->slug ??= static::uniqueSlug($gym->name);

            $gym->trial_ends_at ??= now()->addDays((int) config('gym.trial_days'));
            $gym->saas_plan_id ??= SaasPlan::query()->where('slug', config('gym.default_saas_plan'))->value('id');
        });

        // Currency and time zone always match the gym's country.
        static::saving(function (Gym $gym): void {
            $gym->currency = Country::get($gym->country, 'currency');

            if (! array_key_exists((string) $gym->timezone, Country::get($gym->country, 'timezones'))) {
                $gym->timezone = array_key_first(Country::get($gym->country, 'timezones'));
            }
        });

        static::created(function (Gym $gym): void {
            $gym->branches()->create(['name' => 'Main branch', 'address' => $gym->address, 'phone' => $gym->phone]);
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'gym';
        $slug = $base;
        $i = 2;

        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, config('gym.reserved_slugs'), true)) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default ?? config("gym.defaults.$key"));
    }

    /** A gym's own credential, falling back to the platform-wide config value. */
    public function integration(string $key, string $configKey): ?string
    {
        return data_get($this->integrations, $key) ?: config($configKey);
    }

    /** Public website address: the gym's own domain once it is live, otherwise on the platform. */
    public function websiteUrl(): string
    {
        return $this->hasActiveCustomDomain()
            ? 'https://'.$this->custom_domain
            : route('website.show', $this);
    }

    public function hasActiveCustomDomain(): bool
    {
        return filled($this->custom_domain) && $this->custom_domain_status === 'active';
    }

    public function websiteSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->website, $key, $default);
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->logo_url;
    }

    public function getCurrentTenantLabel(): string
    {
        return 'Current gym';
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /** India: has a GSTIN. Australia: has an ABN and is registered for GST. */
    public function isGstRegistered(): bool
    {
        return $this->country === 'AU'
            ? $this->gst_registered && filled($this->abn)
            : filled($this->gstin);
    }

    public function taxIdLabel(): string
    {
        return $this->country === 'AU' ? 'ABN' : 'GSTIN';
    }

    public function taxId(): ?string
    {
        return $this->country === 'AU' ? $this->abn : $this->gstin;
    }

    /** As printed on invoices; an ABN is grouped 2-3-3-3 (51 824 753 556). */
    public function formattedTaxId(): ?string
    {
        $id = $this->taxId();

        return $id && $this->country === 'AU'
            ? preg_replace('/^(\d{2})(\d{3})(\d{3})(\d{3})$/', '$1 $2 $3 $4', $id)
            : $id;
    }

    // ---- SaaS subscription -------------------------------------------------

    public function saasPlan(): BelongsTo
    {
        return $this->belongsTo(SaasPlan::class);
    }

    public function onTrial(): bool
    {
        return $this->status === 'trial' && $this->trial_ends_at?->isFuture();
    }

    public function hasActiveSaasSubscription(): bool
    {
        return match ($this->status) {
            'trial' => (bool) $this->trial_ends_at?->isFuture(),
            'active' => ! $this->subscription_ends_at || $this->subscription_ends_at->isFuture(),
            default => false,
        };
    }

    /** Whether the gym's SaaS plan allows one more members / branches / staff. */
    public function withinLimit(string $resource): bool
    {
        $limit = $this->saasPlan?->{"max_$resource"};

        if ($limit === null) {
            return true;
        }

        $count = match ($resource) {
            'members' => $this->members()->withoutGlobalScopes()->whereNull('deleted_at')->count(),
            'branches' => $this->branches()->withoutGlobalScopes()->count(),
            'staff' => $this->staffMemberships()->withoutGlobalScopes()->count(),
        };

        return $count < $limit;
    }

    // ---- Numbering ---------------------------------------------------------

    public function takeNumber(string $counter): int
    {
        return DB::transaction(function () use ($counter): int {
            $column = "next_{$counter}_number";
            $current = (int) static::query()->whereKey($this->getKey())->lockForUpdate()->value($column);
            static::query()->whereKey($this->getKey())->update([$column => $current + 1]);
            $this->{$column} = $current + 1;

            return $current;
        });
    }

    public function nextInvoiceNumber(): string
    {
        return sprintf('%s-%s-%05d', $this->invoice_prefix, now()->format('y'), $this->takeNumber('invoice'));
    }

    public function nextReceiptNumber(): string
    {
        return sprintf('RCPT-%06d', $this->takeNumber('receipt'));
    }

    public function nextMemberCode(): string
    {
        return sprintf('%s%04d', $this->member_code_prefix, $this->takeNumber('member'));
    }

    // ---- Relations (also used by Filament tenancy) -------------------------

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(GymUser::class)
            ->withPivot(['id', 'role', 'branch_id', 'is_active'])
            ->withTimestamps();
    }

    public function staffMemberships(): HasMany
    {
        return $this->hasMany(GymUser::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function membershipPlans(): HasMany
    {
        return $this->hasMany(MembershipPlan::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function cashClosings(): HasMany
    {
        return $this->hasMany(CashClosing::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(Measurement::class);
    }

    public function workoutPlans(): HasMany
    {
        return $this->hasMany(WorkoutPlan::class);
    }

    public function dietPlans(): HasMany
    {
        return $this->hasMany(DietPlan::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function staffAttendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class);
    }

    public function trainerRatings(): HasMany
    {
        return $this->hasMany(TrainerRating::class);
    }

    public function messageTemplates(): HasMany
    {
        return $this->hasMany(MessageTemplate::class);
    }

    public function broadcasts(): HasMany
    {
        return $this->hasMany(Broadcast::class);
    }

    public function messageLogs(): HasMany
    {
        return $this->hasMany(MessageLog::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function saasPayments(): HasMany
    {
        return $this->hasMany(SaasPayment::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }
}
