<x-filament-panels::page>
    <x-filament::section heading="Your referral link" description="Gyms that sign up within 60 days of opening this link are credited to you. You earn {{ (float) $partner->commission_rate }}% of every software payment they make.">
        <div style="display: flex; gap: .75rem; align-items: center; flex-wrap: wrap">
            <x-filament::input.wrapper style="flex: 1; min-width: 260px">
                <x-filament::input type="text" readonly :value="$link" id="referral-link" />
            </x-filament::input.wrapper>
            <x-filament::button
                icon="heroicon-o-clipboard-document"
                x-data
                x-on:click="navigator.clipboard.writeText(document.getElementById('referral-link').value); $tooltip('Copied!')"
            >Copy link</x-filament::button>
            <x-filament::button
                color="success"
                icon="heroicon-o-chat-bubble-oval-left"
                tag="a"
                target="_blank"
                :href="'https://wa.me/?text=' . rawurlencode('Run your gym on ' . config('app.name') . ' — members, renewals, WhatsApp reminders and GST invoices. Free trial: ' . $link)"
            >Share on WhatsApp</x-filament::button>
        </div>
        <div style="margin-top: .5rem; opacity: .7; font-size: .875rem">Your code: <strong>{{ $partner->code }}</strong></div>
    </x-filament::section>

    {{ $this->table }}
</x-filament-panels::page>
