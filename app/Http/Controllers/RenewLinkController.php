<?php

namespace App\Http\Controllers;

use App\Models\MembershipPlan;
use App\Services\Payments\RazorpayService;
use App\Support\RenewLink;
use App\Support\Tenancy;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

/** Opens the "renew now" link from a reminder: straight to payment when the gym takes payments online. */
class RenewLinkController extends Controller
{
    public function __invoke(string $code): RedirectResponse|View
    {
        $member = RenewLink::resolve($code);
        abort_unless($member && $member->gym && ! $member->gym->trashed(), 404);

        $gym = $member->gym;

        return Tenancy::forGym($gym, function () use ($member, $gym) {
            $plan = $member->latestSubscription?->plan;
            $plan = $plan?->is_active ? $plan : MembershipPlan::query()->where('is_active', true)->orderBy('price')->first();

            if ($plan && $gym->hasActiveSaasSubscription() && $gym->hasModule('online_payments') && RazorpayService::for($gym)->isConfigured()) {
                try {
                    return redirect()->away(RazorpayService::for($gym)->paymentLinkForRenewal($member, $plan));
                } catch (Throwable $e) {
                    report($e);
                }
            }

            if ($gym->hasModule('member_app') && $member->user_id) {
                return redirect(Filament::getPanel('member')->getLoginUrl());
            }

            return view('public.renew', ['gym' => $gym, 'member' => $member, 'plan' => $plan, 'brand' => $gym->primary_color ?? '#f59e0b']);
        });
    }
}
