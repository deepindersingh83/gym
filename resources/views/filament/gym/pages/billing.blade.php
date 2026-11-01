@php
    $sym = \App\Support\Money::symbol($currency);
    $fmt = fn ($v) => $sym . number_format((float) $v, fmod((float) $v, 1) ? 2 : 0);
    $taxNote = $inclusive ? 'incl. GST' : '+ GST';
    $current = $gym->saasPlan;
@endphp

<x-filament-panels::page>
    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr))">
        <x-filament::section>
            <div style="opacity: .7; font-size: .875rem">Your plan</div>
            <div style="font-size: 1.8rem; font-weight: 800; line-height: 1.2">{{ $current?->name ?? 'No plan' }}</div>

            <div style="margin-top: .5rem">
                @if ($gym->status === 'suspended')
                    <x-filament::badge color="danger">Suspended</x-filament::badge>
                    @if ($gym->suspension_reason)<div style="font-size: .875rem; margin-top: .35rem">{{ $gym->suspension_reason }}</div>@endif
                @elseif ($gym->onTrial())
                    <x-filament::badge color="info">Free trial</x-filament::badge>
                    <div style="font-size: .875rem; margin-top: .35rem">Ends {{ $gym->trial_ends_at->format('d M Y') }} ({{ $gym->trial_ends_at->diffForHumans() }})</div>
                @elseif ($gym->inGracePeriod())
                    <x-filament::badge color="danger">Payment overdue</x-filament::badge>
                    <div style="font-size: .875rem; margin-top: .35rem">Ended {{ $gym->subscription_ends_at->format('d M Y') }}. Changes lock on {{ $graceEndsAt->format('d M Y') }}.</div>
                @elseif ($gym->hasActiveSaasSubscription())
                    <x-filament::badge color="success">Active</x-filament::badge>
                    @if ($gym->subscription_ends_at)
                        <div style="font-size: .875rem; margin-top: .35rem">Paid until {{ $gym->subscription_ends_at->format('d M Y') }}</div>
                    @endif
                @else
                    <x-filament::badge color="danger">Ended</x-filament::badge>
                    <div style="font-size: .875rem; margin-top: .35rem">Changes are locked until you renew.</div>
                @endif
            </div>

            <div style="margin-top: 1rem; padding-top: .75rem; border-top: 1px solid rgba(127,127,127,.2); font-size: .875rem">
                @if ($gym->auto_renew)
                    <div style="display: flex; align-items: center; gap: .4rem; margin-bottom: .5rem">
                        <x-filament::icon icon="heroicon-o-arrow-path" style="width: 1rem; height: 1rem; color: rgb(22 163 74)" />
                        <strong>Auto-renew is on</strong> ({{ $gym->billing_period ?? 'monthly' }})
                    </div>
                    {{ $this->cancelAutoRenewAction }}
                @elseif ($online)
                    <div style="opacity: .75; margin-bottom: .5rem">Auto-renew is off. Turn it on so your gym never gets locked.</div>
                    {{ $this->autoRenewAction }}
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            <div style="opacity: .7; font-size: .875rem; margin-bottom: .5rem">Usage</div>
            @foreach ($usage as $label => [$used, $limit])
                @php($pct = $limit ? min(100, round($used / $limit * 100)) : 0)
                <div style="margin-bottom: .75rem">
                    <div style="display: flex; justify-content: space-between; font-size: .875rem">
                        <span>{{ $label }}</span>
                        <span><strong>{{ number_format($used) }}</strong> / {{ $limit ? number_format($limit) : 'Unlimited' }}</span>
                    </div>
                    @if ($limit)
                        <div style="height: 6px; border-radius: 3px; background: rgba(127,127,127,.18); margin-top: 4px; overflow: hidden">
                            <div style="height: 100%; width: {{ $pct }}%; background: {{ $pct >= 90 ? 'rgb(220 38 38)' : 'rgb(245 158 11)' }}"></div>
                        </div>
                    @endif
                </div>
            @endforeach
            <div style="font-size: .8rem; opacity: .65">Message credits: {{ number_format($gym->message_credits) }}</div>
        </x-filament::section>
    </div>

    @unless ($online)
        <x-filament::callout color="info" icon="heroicon-o-information-circle" heading="Online payment is not set up yet"
            :description="'Contact us to renew or change your plan' . (($support['email'] ?? null) ? ': ' . $support['email'] : '') . (($support['phone'] ?? null) ? ' · ' . $support['phone'] : '') . '.'" />
    @endunless

    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr))">
        @foreach ($plans as $plan)
            @php($isCurrent = $plan->id === $gym->saas_plan_id)
            <x-filament::section>
                <div style="display: flex; justify-content: space-between; align-items: center">
                    <div style="font-size: 1.25rem; font-weight: 800">{{ $plan->name }}</div>
                    @if ($isCurrent)<x-filament::badge>Current</x-filament::badge>@endif
                </div>
                <div style="margin: .5rem 0">
                    <span style="font-size: 1.5rem; font-weight: 800">{{ $fmt($plan->price($currency, 'monthly')) }}</span><span style="opacity: .65">/month</span>
                    <div style="font-size: .8rem; opacity: .7">or {{ $fmt($plan->price($currency, 'yearly')) }}/year · {{ $taxNote }}</div>
                </div>
                <div style="font-size: .8rem; opacity: .8; margin-bottom: .5rem">
                    {{ $plan->max_members ? number_format($plan->max_members) . ' members' : 'Unlimited members' }} ·
                    {{ $plan->max_branches ? $plan->max_branches . ' ' . str('branch')->plural($plan->max_branches) : 'Unlimited branches' }} ·
                    {{ $plan->max_staff ? $plan->max_staff . ' staff' : 'Unlimited staff' }}
                </div>
                <ul style="font-size: .85rem; list-style: none; padding: 0; margin: 0 0 1rem; display: grid; gap: .2rem">
                    @foreach ($modules as $key => [$label])
                        @php($has = $plan->includes($key))
                        <li style="display: flex; gap: .35rem; align-items: center; {{ $has ? '' : 'opacity: .4; text-decoration: line-through' }}">
                            <x-filament::icon :icon="$has ? 'heroicon-m-check' : 'heroicon-m-x-mark'" style="width: .9rem; height: .9rem; flex: none" />{{ $label }}
                        </li>
                    @endforeach
                </ul>
                @if ($online && ! ($isCurrent && $gym->auto_renew))
                    {{ ($this->checkoutAction)(['plan' => $plan->id]) }}
                @endif
            </x-filament::section>
        @endforeach
    </div>

    {{ $this->table }}
</x-filament-panels::page>
