@php($money = fn ($v) => \App\Support\Money::format($v))

<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; align-items: center; justify-content: space-between">
            <div style="display: grid; gap: .5rem; min-width: 240px">
                <div style="font-size: 1.4rem; font-weight: 700">{{ $member->name }}</div>
                <div style="opacity: .7">Member ID {{ $member->member_code }}</div>

                @if ($subscription)
                    <div>
                        <strong>{{ $subscription->plan?->name }}</strong>
                        · {{ $subscription->start_date->format('d M Y') }} to {{ $subscription->end_date->format('d M Y') }}
                    </div>
                    <div>
                        @if ($subscription->days_left >= 0 && $subscription->status === \App\Enums\SubscriptionStatus::Active)
                            <x-filament::badge :color="$subscription->days_left <= 7 ? 'warning' : 'success'">
                                Renewal date {{ $subscription->end_date->format('d M Y') }} · {{ $subscription->days_left }} days left
                            </x-filament::badge>
                        @else
                            <x-filament::badge color="danger">Membership ended {{ $subscription->end_date->format('d M Y') }}</x-filament::badge>
                        @endif
                    </div>
                    @if ($subscription->auto_renew)
                        <div><x-filament::badge color="gray">UPI AutoPay is on</x-filament::badge></div>
                    @endif
                @else
                    <x-filament::badge color="gray">No membership yet — visit the front desk or renew online</x-filament::badge>
                @endif

                @if ($member->balance_due > 0)
                    <div><x-filament::badge color="danger">Pending dues {{ $money($member->balance_due) }}</x-filament::badge></div>
                @endif

                @if ($member->trainer)
                    <div style="opacity: .8">Trainer: {{ $member->trainer->name }}</div>
                @endif
            </div>

            <div style="text-align: center">
                <img src="{{ $qr }}" alt="Check-in QR code" style="width: 170px; height: 170px; background: #fff; padding: 6px; border-radius: 12px">
                <div style="font-size: .8rem; opacity: .7">Show this at the front desk to check in</div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
