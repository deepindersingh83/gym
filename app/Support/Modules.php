<?php

namespace App\Support;

use App\Models\Gym;

/** Features a software plan can switch on or off for a gym. */
class Modules
{
    public const ALL = [
        'member_app' => ['Member app', 'Members sign in to see their membership, QR check-in, payments, receipts and progress.'],
        'website' => ['Gym website', 'Public website with plans, trainers, gallery and free-trial booking.'],
        'custom_domain' => ['Own domain', 'Website and member app on the gym\'s own domain with SSL.'],
        'online_payments' => ['Online payments', 'Razorpay payment links, online renewals and UPI AutoPay.'],
        'lead_ads' => ['Social media leads', 'Facebook / Instagram lead ads and the lead webhook.'],
        'broadcasts' => ['Broadcasts', 'Send announcements and offers to all, active or lapsed members.'],
        'google_reviews' => ['Google review requests', 'Ask members for a review after their Nth visit.'],
        'workout_diet' => ['Workout & diet plans', 'Trainers assign workout and diet plans members see in the app.'],
        'exports' => ['Excel & PDF exports', 'Download members, payments and dues reports.'],
        'audit_log' => ['Audit log', 'See who changed what and when.'],
    ];

    /** Suggested modules for the standard plans. */
    public const DEFAULTS = [
        'basic' => ['member_app', 'exports'],
        'pro' => ['member_app', 'exports', 'website', 'online_payments', 'lead_ads', 'broadcasts', 'google_reviews', 'workout_diet', 'audit_log'],
        'enterprise' => null, // everything
    ];

    /** @return array<string, string> key => label */
    public static function options(): array
    {
        return array_map(fn ($m) => $m[0], self::ALL);
    }

    /** @return array<string, string> key => description */
    public static function descriptions(): array
    {
        return array_map(fn ($m) => $m[1], self::ALL);
    }

    public static function label(string $module): string
    {
        return self::ALL[$module][0] ?? $module;
    }

    public static function enabled(string $module, ?Gym $gym = null): bool
    {
        $gym ??= Tenancy::gym();

        return $gym === null || $gym->hasModule($module);
    }
}
