@php
    $m = fn ($v) => \App\Support\Money::format($v, $currency);
    $th = 'text-align: left; opacity: .65; font-weight: 600; padding: .35rem .5rem .35rem 0';
    $td = 'padding: .45rem .5rem .45rem 0; border-top: 1px solid rgba(127,127,127,.18)';
    $days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
@endphp

<x-filament-panels::page>
    <div style="display: flex; gap: .5rem; flex-wrap: wrap; align-items: center">
        <span style="font-size: .875rem; opacity: .7">Period:</span>
        @foreach ([30 => '30 days', 90 => '90 days', 180 => '6 months', 365 => '12 months'] as $value => $label)
            <x-filament::button size="sm" :color="$this->days === $value ? 'primary' : 'gray'" wire:click="$set('days', {{ $value }})">{{ $label }}</x-filament::button>
        @endforeach
    </div>

    <x-filament::section heading="Member retention" description="Of the memberships that ended each month, how many renewed within 30 days.">
        <div style="display: grid; gap: .75rem; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr))">
            @foreach ($retention as $row)
                <div style="padding: .75rem; border-radius: 12px; background: rgba(127,127,127,.08)">
                    <div style="font-size: .8rem; opacity: .7">{{ $row['month'] }}</div>
                    <div style="font-size: 1.5rem; font-weight: 800; {{ $row['ended'] && $row['rate'] < 50 ? 'color: rgb(220 38 38)' : '' }}">{{ $row['ended'] ? $row['rate'].'%' : '—' }}</div>
                    <div style="font-size: .8rem; opacity: .7">{{ $row['renewed'] }} of {{ $row['ended'] }} renewed</div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament::section heading="Busiest hours" :description="$hours['total'] ? number_format($hours['total']).' check-ins in the last '.min($this->days, 90).' days. Busiest: '.$hours['peak'].'.' : 'No check-ins yet.'">
        @if ($hours['total'])
            <div style="overflow-x: auto">
                <table style="border-collapse: separate; border-spacing: 3px; font-size: .7rem">
                    <tr>
                        <td></td>
                        @foreach (range(5, 23) as $h)<td style="text-align: center; opacity: .6">{{ \Illuminate\Support\Carbon::createFromTime($h)->format('ga') }}</td>@endforeach
                    </tr>
                    @foreach ($hours['grid'] as $day => $row)
                        <tr>
                            <td style="padding-right: .4rem; opacity: .7">{{ $days[$day - 1] }}</td>
                            @foreach (range(5, 23) as $h)
                                @php($n = $row[$h])
                                <td title="{{ $days[$day - 1] }} {{ \Illuminate\Support\Carbon::createFromTime($h)->format('g A') }}: {{ $n }} check-ins"
                                    style="width: 26px; height: 22px; border-radius: 4px; text-align: center; background: rgba(245, 158, 11, {{ $n ? 0.12 + 0.88 * $n / $hours['max'] : 0.04 }}); {{ $n / max(1, $hours['max']) > .6 ? 'color: #fff; font-weight: 700' : '' }}">{{ $n ?: '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </x-filament::section>

    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr))">
        <x-filament::section heading="Revenue per trainer" description="Payments from each trainer's assigned members in the period.">
            <table style="width: 100%; font-size: .875rem; border-collapse: collapse">
                <tr><th style="{{ $th }}">Trainer</th><th style="{{ $th }}">Active members</th><th style="{{ $th }}; text-align: right">Collected</th><th style="{{ $th }}; text-align: right">Rating</th></tr>
                @forelse ($trainers as $row)
                    <tr>
                        <td style="{{ $td }}">{{ $row['trainer'] }}</td>
                        <td style="{{ $td }}">{{ $row['members'] }}</td>
                        <td style="{{ $td }}; text-align: right">{{ $m($row['collected']) }}</td>
                        <td style="{{ $td }}; text-align: right">{{ $row['rating'] ? '★ '.$row['rating'].' ('.$row['ratings'].')' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="{{ $td }}; opacity: .6">Assign trainers to members to see this.</td></tr>
                @endforelse
            </table>
        </x-filament::section>

        <x-filament::section heading="Enquiries by source" description="Enquiries received in the period and how many joined.">
            <table style="width: 100%; font-size: .875rem; border-collapse: collapse">
                <tr><th style="{{ $th }}">Source</th><th style="{{ $th }}">Enquiries</th><th style="{{ $th }}">Trials</th><th style="{{ $th }}">Joined</th><th style="{{ $th }}; text-align: right">Conversion</th></tr>
                @forelse ($leads as $row)
                    <tr>
                        <td style="{{ $td }}">{{ $row['source'] }}</td>
                        <td style="{{ $td }}">{{ $row['leads'] }}</td>
                        <td style="{{ $td }}">{{ $row['trials'] }}</td>
                        <td style="{{ $td }}">{{ $row['joined'] }}</td>
                        <td style="{{ $td }}; text-align: right; font-weight: 700">{{ $row['rate'] }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="{{ $td }}; opacity: .6">No enquiries in this period.</td></tr>
                @endforelse
            </table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
