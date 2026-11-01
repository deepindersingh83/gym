@php
    $gym = \App\Support\Tenancy::gym();
    $isOwner = \App\Support\Tenancy::role() === \App\Enums\StaffRole::Owner;
    $billingUrl = $gym ? \App\Filament\Gym\Pages\Billing::getUrl(tenant: $gym) : null;
    $action = $isOwner ? 'Renew now in Plan & billing.' : 'Ask the gym owner to renew.';
@endphp

@if (! $gym || request()->routeIs('filament.gym.pages.billing'))
    {{-- The billing page shows its own status. --}}
@elseif ($gym->status === 'suspended')
    <x-filament::callout color="danger" icon="heroicon-o-no-symbol" heading="This account is suspended"
        :description="($gym->suspension_reason ? $gym->suspension_reason . '. ' : '') . 'You can view your data, but changes are locked. Please contact support.'"
        style="margin-bottom: 1rem" />
@elseif (! $gym->hasActiveSaasSubscription())
    <x-filament::callout
        color="danger"
        icon="heroicon-o-lock-closed"
        :heading="'Your ' . ($gym->status === 'trial' ? 'free trial' : 'plan') . ' has ended'"
        :description="'You can still view your data, but changes are locked until the plan is renewed. ' . $action"
        style="margin-bottom: 1rem"
    >
        @if ($isOwner)
            <x-slot name="footer"><x-filament::button tag="a" :href="$billingUrl" color="danger">Renew now</x-filament::button></x-slot>
        @endif
    </x-filament::callout>
@elseif ($gym->inGracePeriod())
    <x-filament::callout
        color="danger"
        icon="heroicon-o-exclamation-triangle"
        :heading="'Your plan ended on ' . $gym->subscription_ends_at->format('d M Y')"
        :description="'Changes will be locked from ' . $gym->subscription_ends_at->copy()->addDays((int) config('gym.billing.grace_days'))->format('d M Y') . '. ' . $action"
        style="margin-bottom: 1rem"
    >
        @if ($isOwner)
            <x-slot name="footer"><x-filament::button tag="a" :href="$billingUrl" color="danger">Renew now</x-filament::button></x-slot>
        @endif
    </x-filament::callout>
@elseif ($gym->onTrial() && now()->diffInDays($gym->trial_ends_at) <= 7)
    <x-filament::callout
        color="warning"
        icon="heroicon-o-clock"
        :heading="'Free trial ends ' . $gym->trial_ends_at->diffForHumans()"
        :description="'Your trial runs until ' . $gym->trial_ends_at->format('d M Y') . '.' . ($isOwner ? ' Choose a plan to keep going.' : '')"
        style="margin-bottom: 1rem"
    >
        @if ($isOwner)
            <x-slot name="footer"><x-filament::button tag="a" :href="$billingUrl" color="warning">Choose a plan</x-filament::button></x-slot>
        @endif
    </x-filament::callout>
@elseif ($gym->status === 'active' && ! $gym->auto_renew && $gym->subscription_ends_at && now()->diffInDays($gym->subscription_ends_at) <= 7)
    <x-filament::callout
        color="warning"
        icon="heroicon-o-clock"
        :heading="'Your plan ends ' . $gym->subscription_ends_at->diffForHumans()"
        :description="'Paid until ' . $gym->subscription_ends_at->format('d M Y') . '. ' . ($isOwner ? 'Renew or turn on auto-renew to avoid interruption.' : 'Ask the gym owner to renew.')"
        style="margin-bottom: 1rem"
    >
        @if ($isOwner)
            <x-slot name="footer"><x-filament::button tag="a" :href="$billingUrl" color="warning">Renew</x-filament::button></x-slot>
        @endif
    </x-filament::callout>
@endif
