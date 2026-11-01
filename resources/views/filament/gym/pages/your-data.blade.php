<x-filament-panels::page>
    <x-filament::section heading="Download everything" icon="heroicon-o-arrow-down-tray">
        <p style="font-size: .9rem; margin-bottom: 1rem">
            One ZIP file with a spreadsheet (CSV) for each part of your gym: members, memberships, payments, check-ins,
            measurements, enquiries, expenses, staff and messages, plus member photos and website images.
            You own this data and can take it with you at any time.
        </p>
        {{ $this->downloadAction }}
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
