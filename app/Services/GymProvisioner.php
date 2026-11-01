<?php

namespace App\Services;

use App\Enums\MessageEvent;
use App\Enums\PlanType;
use App\Enums\StaffRole;
use App\Models\Gym;
use App\Models\User;
use App\Support\Country;
use Illuminate\Support\Facades\DB;

/**
 * Creates a ready-to-use gym: the gym itself (with its main branch), its owner,
 * starter membership plans and default reminder templates. Used by the owner's
 * signup wizard and by the super admin's "Add gym".
 */
class GymProvisioner
{
    /** Suggested starting prices; owners change them under Plans. */
    public const STARTER_PRICES = [
        'INR' => ['monthly' => 1000, 'quarterly' => 2700, 'half_yearly' => 5000, 'yearly' => 9000],
        'AUD' => ['monthly' => 60, 'quarterly' => 165, 'half_yearly' => 300, 'yearly' => 550],
    ];

    /**
     * @param  array<string, mixed>  $attributes  Gym columns.
     * @param  list<string>  $starterPlans  PlanType values to create plans for.
     */
    public function provision(array $attributes, User $owner, array $starterPlans = ['monthly', 'quarterly', 'yearly']): Gym
    {
        return DB::transaction(function () use ($attributes, $owner, $starterPlans) {
            $gym = Gym::create(['status' => 'trial', 'onboarding_completed' => true, ...$attributes]);

            $gym->users()->attach($owner->id, ['role' => StaffRole::Owner->value, 'joined_on' => today()]);

            $prices = self::STARTER_PRICES[$gym->currency] ?? self::STARTER_PRICES['INR'];

            foreach (array_intersect($starterPlans, array_keys($prices)) as $type) {
                $planType = PlanType::from($type);
                [$value, $unit] = $planType->defaults();

                $gym->membershipPlans()->create([
                    'name' => str($planType->getLabel())->before(' ('),
                    'type' => $planType,
                    'duration_value' => $value,
                    'duration_unit' => $unit,
                    'price' => $prices[$type],
                    'gst_rate' => Country::get($gym->country, 'tax_rate'),
                ]);
            }

            foreach ([MessageEvent::RenewalReminder, MessageEvent::Expired, MessageEvent::DuesReminder, MessageEvent::Birthday] as $event) {
                $gym->messageTemplates()->create(['event' => $event, 'channel' => 'whatsapp', 'body' => $event->defaultBody()]);
            }

            if ($welcome = (int) config('gym.credits.welcome')) {
                app(Credits::class)->add($gym, $welcome, 'adjustment', 'Welcome credits');
            }

            return $gym;
        });
    }
}
