<?php

namespace App\Services\Compliance;

use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** A member's right to see and remove the personal data a gym holds about them. */
class MemberData
{
    /** Personal fields cleared on erasure. Payments and invoices stay (tax law), linked to "Erased member". */
    protected const PERSONAL = [
        'email', 'gender', 'date_of_birth', 'anniversary_date', 'photo_path', 'address', 'occupation', 'blood_group',
        'height_cm', 'weight_kg', 'emergency_contact_name', 'emergency_contact_phone', 'health_questionnaire',
        'medical_conditions', 'goal', 'target_weight_kg', 'notes',
    ];

    /** Everything about the member, readable as JSON. */
    public function export(Member $member): array
    {
        $member->loadMissing(['gym', 'subscriptions.plan', 'payments', 'measurements', 'attendances', 'workoutPlans', 'dietPlans']);

        return [
            'exported_at' => now()->toIso8601String(),
            'gym' => ['name' => $member->gym->name, 'email' => $member->gym->email, 'phone' => $member->gym->phone],
            'member' => collect($member->attributesToArray())->except(['id', 'gym_id', 'branch_id', 'user_id', 'trainer_id', 'deleted_at'])->all(),
            'memberships' => $member->subscriptions->map(fn ($s) => [
                'invoice' => $s->invoice_number,
                'plan' => $s->plan?->name,
                'starts_on' => $s->start_date?->toDateString(),
                'ends_on' => $s->end_date?->toDateString(),
                'total' => $s->total_amount,
                'status' => $s->status?->value ?? $s->status,
            ])->all(),
            'payments' => $member->payments->map(fn ($p) => [
                'receipt' => $p->receipt_number,
                'amount' => $p->amount,
                'method' => $p->method?->value ?? $p->method,
                'paid_at' => $p->paid_at?->toIso8601String(),
            ])->all(),
            'measurements' => $member->measurements->map(fn ($m) => collect($m->attributesToArray())->except(['id', 'gym_id', 'member_id', 'recorded_by'])->all())->all(),
            'check_ins' => $member->attendances->map(fn ($a) => $a->checked_in_at?->toIso8601String())->all(),
            'workout_plans' => $member->workoutPlans->pluck('name')->all(),
            'diet_plans' => $member->dietPlans->pluck('name')->all(),
        ];
    }

    /**
     * Removes the member's personal details, photos, body measurements, plans and app login.
     * Keeps an anonymous record of memberships and payments, which the gym must keep for tax.
     */
    public function erase(Member $member, ?User $by = null): void
    {
        DB::transaction(function () use ($member, $by) {
            if ($member->photo_path) {
                Storage::disk('public')->delete($member->photo_path);
            }

            $member->measurements()->delete();
            $member->workoutPlans()->delete();
            $member->dietPlans()->delete();
            $member->trainerRatings()->update(['comment' => null]);
            DB::table('message_logs')->where('member_id', $member->id)->update(['recipient' => '[erased]', 'body' => '[erased]']);
            DB::table('leads')->where('member_id', $member->id)->update(['name' => 'Erased member', 'phone' => null, 'email' => null, 'notes' => null, 'raw' => null]);

            $member->forceFill(array_fill_keys(self::PERSONAL, null) + [
                'name' => 'Erased member '.$member->member_code,
                // Phone is required; keep a unique placeholder that cannot be dialled or matched.
                'phone' => 'erased-'.$member->id,
                'user_id' => null,
                'is_active' => false,
                'erased_at' => now(),
            ])->save();

            activity()->causedBy($by)->performedOn($member)->tap(fn ($a) => $a->gym_id = $member->gym_id)
                ->log('Erased member personal data');
        });
    }
}
