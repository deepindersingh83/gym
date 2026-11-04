<x-filament-panels::page>
    <x-filament::section heading="Download everything" icon="heroicon-o-arrow-down-tray">
        <p style="font-size: .9rem; margin-bottom: 1rem">
            We prepare one ZIP file with a spreadsheet (CSV) for each part of your gym: members, memberships, payments, check-ins,
            measurements, enquiries, expenses, staff and messages, plus member photos and website images.
            You own this data and can take it with you at any time. Large gyms can take a few minutes; we email you the link.
        </p>
        {{ $this->downloadAction }}

        @if ($exports->isNotEmpty())
            <table style="width: 100%; font-size: .875rem; margin-top: 1rem; border-collapse: collapse">
                @foreach ($exports as $export)
                    <tr style="border-top: 1px solid rgba(127,127,127,.18)">
                        <td style="padding: .45rem 0">{{ $export->created_at->format('d M Y, h:i A') }}</td>
                        <td>
                            @if ($export->isReady())
                                <a href="{{ $export->downloadUrl() }}" style="text-decoration: underline">Download ({{ number_format($export->size / 1048576, 1) }} MB)</a>
                                <span style="opacity: .6">· until {{ $export->expires_at->format('d M Y') }}</span>
                            @elseif ($export->status === 'pending')
                                <span style="opacity: .7">Preparing… refresh in a minute</span>
                            @elseif ($export->status === 'failed')
                                <span style="color: rgb(220 38 38)">Failed. Please try again or contact support.</span>
                            @else
                                <span style="opacity: .6">Expired</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif
    </x-filament::section>

    <x-filament::section heading="Members' privacy" icon="heroicon-o-shield-check">
        <ul style="font-size: .9rem; display: grid; gap: .4rem; list-style: disc; padding-left: 1.25rem">
            <li>Members agree to you storing their details when they join online. When staff add a member, tick "Member agreed" on the form.</li>
            <li>A member can download their own data from the member app, or you can do it from their profile (Download data).</li>
            <li>If a member asks to be forgotten, open their profile and use <strong>Erase personal data</strong>. Their payments stay as anonymous records because tax law requires them.</li>
        </ul>
    </x-filament::section>

    <x-filament::section heading="Close account" icon="heroicon-o-trash" :collapsed="true" collapsible>
        <p style="font-size: .9rem; margin-bottom: 1rem">
            Closing stops all sign-ins and automatic payments. Everything is deleted for good after {{ \App\Services\Compliance\GymClosure::KEEP_DAYS }} days.
        </p>
        {{ $this->closeAction }}
    </x-filament::section>
</x-filament-panels::page>
