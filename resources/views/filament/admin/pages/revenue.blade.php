@php
    $m = fn ($v) => \App\Support\Money::format($v, $currency);
    $card = 'display: grid; gap: .25rem';
    $label = 'opacity: .7; font-size: .8rem';
    $big = 'font-size: 1.6rem; font-weight: 800; line-height: 1.15';
    $small = 'font-size: .8rem; opacity: .7';
@endphp

<x-filament-panels::page>
    <div style="display: flex; gap: .5rem; align-items: center; flex-wrap: wrap">
        @foreach (['INR' => 'India (₹)', 'AUD' => 'Australia (A$)'] as $code => $name)
            <x-filament::button tag="a" :href="static::getUrl(['currency' => $code])" :color="$currency === $code ? 'primary' : 'gray'" size="sm">{{ $name }}</x-filament::button>
        @endforeach
        <span style="font-size: .8rem; opacity: .65; margin-left: .25rem">Amounts are before GST. Currencies are kept separate.</span>
    </div>

    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr))">
        <x-filament::section><div style="{{ $card }}">
            <span style="{{ $label }}">Monthly recurring revenue</span>
            <span style="{{ $big }}">{{ $m($mrr) }}</span>
            <span style="{{ $small }}">{{ $paying }} paying {{ str('gym')->plural($paying) }}</span>
        </div></x-filament::section>
        <x-filament::section><div style="{{ $card }}">
            <span style="{{ $label }}">Annual run rate</span>
            <span style="{{ $big }}">{{ $m($arr) }}</span>
            <span style="{{ $small }}">MRR × 12</span>
        </div></x-filament::section>
        <x-filament::section><div style="{{ $card }}">
            <span style="{{ $label }}">Received this month</span>
            <span style="{{ $big }}">{{ $m($thisMonth) }}</span>
            <span style="{{ $small }}">Last month {{ $m($lastMonth) }}</span>
        </div></x-filament::section>
        <x-filament::section><div style="{{ $card }}">
            <span style="{{ $label }}">Last 12 months</span>
            <span style="{{ $big }}">{{ $m($last12) }}</span>
            <span style="{{ $small }}">Plans and message credits</span>
        </div></x-filament::section>
        <x-filament::section><div style="{{ $card }}">
            <span style="{{ $label }}">Churn (30 days)</span>
            <span style="{{ $big }}; {{ $churn['rate'] >= 5 ? 'color: rgb(220 38 38)' : '' }}">{{ $churn['rate'] }}%</span>
            <span style="{{ $small }}">{{ $churn['lost'] }} {{ str('gym')->plural($churn['lost']) }} did not renew</span>
        </div></x-filament::section>
        <x-filament::section><div style="{{ $card }}">
            <span style="{{ $label }}">Trial → paid (90 days)</span>
            <span style="{{ $big }}">{{ $conversion['rate'] }}%</span>
            <span style="{{ $small }}">{{ $conversion['paid'] }} of {{ $conversion['ended'] }} ended trials paid</span>
        </div></x-filament::section>
    </div>

    @livewire(\App\Filament\Admin\Widgets\RevenueChart::class, ['currency' => $currency], key('chart-' . $currency))

    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr))">
        <x-filament::section heading="By plan" description="Paying gyms today; revenue over the last 12 months.">
            <table style="width: 100%; font-size: .875rem; border-collapse: collapse">
                <thead><tr style="text-align: left; opacity: .65"><th style="padding: .35rem 0">Plan</th><th>Gyms</th><th style="text-align: right">MRR</th><th style="text-align: right">12 months</th></tr></thead>
                <tbody>
                    @forelse ($byPlan as $row)
                        <tr style="border-top: 1px solid rgba(127,127,127,.18)"><td style="padding: .45rem 0">{{ $row['plan'] }}</td><td>{{ $row['gyms'] }}</td><td style="text-align: right">{{ $m($row['mrr']) }}</td><td style="text-align: right">{{ $m($row['revenue']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" style="padding: .75rem 0; opacity: .6">No paying gyms yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>

        <x-filament::section heading="By partner" description="Software revenue from referred gyms, last 12 months.">
            <table style="width: 100%; font-size: .875rem; border-collapse: collapse">
                <thead><tr style="text-align: left; opacity: .65"><th style="padding: .35rem 0">Partner</th><th>Gyms</th><th style="text-align: right">Revenue</th><th style="text-align: right">Commission (unpaid)</th></tr></thead>
                <tbody>
                    @forelse ($byPartner as $row)
                        <tr style="border-top: 1px solid rgba(127,127,127,.18)"><td style="padding: .45rem 0">{{ $row['partner'] }}</td><td>{{ $row['gyms'] }}</td><td style="text-align: right">{{ $m($row['revenue']) }}</td><td style="text-align: right">{{ $m($row['commission']) }} ({{ $m($row['unpaid']) }})</td></tr>
                    @empty
                        <tr><td colspan="4" style="padding: .75rem 0; opacity: .6">No partner revenue yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-filament::section>
    </div>

    <x-filament::section heading="Renewals due in the next 30 days" :description="$renewals->isEmpty() ? null : $renewals->where('auto_renew', true)->count() . ' of ' . $renewals->count() . ' renew automatically.'">
        <table style="width: 100%; font-size: .875rem; border-collapse: collapse">
            <thead><tr style="text-align: left; opacity: .65"><th style="padding: .35rem 0">Gym</th><th>Plan</th><th>Ends</th><th>Auto-renew</th><th style="text-align: right">Monthly value</th></tr></thead>
            <tbody>
                @forelse ($renewals as $gym)
                    <tr style="border-top: 1px solid rgba(127,127,127,.18)">
                        <td style="padding: .45rem 0">
                            @if (\App\Support\AdminAccess::can('gyms.edit'))
                                <a href="{{ \App\Filament\Admin\Resources\Gyms\GymResource::getUrl('edit', ['record' => $gym]) }}" style="text-decoration: underline">{{ $gym->name }}</a>
                            @else
                                {{ $gym->name }}
                            @endif
                        </td>
                        <td>{{ $gym->saasPlan?->name ?? '—' }}</td>
                        <td>{{ $gym->subscription_ends_at->format('d M Y') }} <span style="opacity: .6">({{ $gym->subscription_ends_at->diffForHumans() }})</span></td>
                        <td>@if ($gym->auto_renew)<x-filament::badge color="success">On</x-filament::badge>@else<x-filament::badge color="warning">Off</x-filament::badge>@endif</td>
                        <td style="text-align: right">{{ $m($report->monthlyValue($gym)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="padding: .75rem 0; opacity: .6">No renewals due in the next 30 days.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-filament::section>
</x-filament-panels::page>
