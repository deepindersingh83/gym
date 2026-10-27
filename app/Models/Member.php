<?php

namespace App\Models;

use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToGym;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Member extends Model
{
    use Auditable, BelongsToGym, HasFactory, SoftDeletes;

    protected static bool $scopedByBranch = true;

    protected $guarded = ['id'];

    protected $attributes = [
        'source' => 'walk_in',
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'anniversary_date' => 'date',
            'joined_on' => 'date',
            'health_questionnaire' => 'array',
            'is_active' => 'boolean',
            'height_cm' => 'decimal:1',
            'weight_kg' => 'decimal:1',
            'target_weight_kg' => 'decimal:1',
        ];
    }

    protected static function booted(): void
    {
        // Trainers only see the members assigned to them.
        static::addGlobalScope('trainer', function (Builder $query): void {
            if (Tenancy::role() === StaffRole::Trainer) {
                $query->where($query->getModel()->qualifyColumn('trainer_id'), auth()->id());
            }
        });

        static::creating(function (Member $member): void {
            $member->joined_on ??= today();
            $member->member_code ??= Gym::query()->findOrFail($member->gym_id)->nextMemberCode();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', SubscriptionStatus::Active)
            ->latestOfMany('end_date');
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany('end_date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
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

    public function trainerRatings(): HasMany
    {
        return $this->hasMany(TrainerRating::class);
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function getBmiAttribute(): ?float
    {
        return Measurement::bmiFor($this->weight_kg, $this->height_cm);
    }

    /** Total unpaid balance across all of the member's subscriptions. */
    public function getBalanceDueAttribute(): float
    {
        return round((float) $this->subscriptions()
            ->where('status', '!=', SubscriptionStatus::Cancelled)
            ->selectRaw('COALESCE(SUM(total_amount - amount_paid), 0) as due')
            ->value('due'), 2);
    }

    public function scopeWithBalanceDue(Builder $query): Builder
    {
        return $query->withSum(['subscriptions as total_billed' => fn ($q) => $q->where('status', '!=', SubscriptionStatus::Cancelled)], 'total_amount')
            ->withSum(['subscriptions as total_paid' => fn ($q) => $q->where('status', '!=', SubscriptionStatus::Cancelled)], 'amount_paid');
    }

    /** Value encoded in the member's check-in QR code. */
    public function getCheckInCodeAttribute(): string
    {
        return 'GYM:'.$this->gym_id.':'.$this->member_code;
    }
}
