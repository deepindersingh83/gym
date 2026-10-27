@php($gym = \App\Support\Tenancy::gym())

@if ($gym && ! $gym->hasActiveSaasSubscription())
    <x-filament::callout
        color="danger"
        icon="heroicon-o-lock-closed"
        :heading="'Your ' . ($gym->status === 'trial' ? 'free trial' : 'plan') . ' has ended'"
        description="You can still view your data, but changes are locked until the plan is renewed. Contact support to continue."
        style="margin-bottom: 1rem"
    />
@elseif ($gym?->onTrial() && now()->diffInDays($gym->trial_ends_at) <= 7)
    <x-filament::callout
        color="warning"
        icon="heroicon-o-clock"
        :heading="'Free trial ends ' . $gym->trial_ends_at->diffForHumans()"
        :description="'Your trial runs until ' . $gym->trial_ends_at->format('d M Y') . '.'"
        style="margin-bottom: 1rem"
    />
@endif
