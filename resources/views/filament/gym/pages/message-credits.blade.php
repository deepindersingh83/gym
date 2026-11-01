<x-filament-panels::page>
    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr))">
        <x-filament::section>
            <div style="opacity: .7; font-size: .875rem">Credits left</div>
            <div style="font-size: 2.5rem; font-weight: 800; line-height: 1.1; {{ $balance < config('gym.credits.low_balance') ? 'color: rgb(var(--danger-600, 220 38 38))' : '' }}">{{ number_format($balance) }}</div>
            <div style="opacity: .7; font-size: .875rem; margin-top: .5rem">
                WhatsApp: {{ $ownWhatsApp ? 'free (your own account)' : $costs['whatsapp'].' credit per message' }}<br>
                SMS: {{ $ownSms ? 'free (your own account)' : $costs['sms'].' credit per message' }}<br>
                Email and member-app notifications: free
            </div>
        </x-filament::section>

        @foreach ($packs as $credits => $price)
            <x-filament::section>
                <div style="font-size: 1.6rem; font-weight: 800">{{ number_format($credits) }}</div>
                <div style="opacity: .7">credits</div>
                <div style="font-size: 1.15rem; font-weight: 600; margin: .5rem 0">{{ \App\Support\Money::symbol($currency).number_format($price) }}
                    <span style="opacity: .6; font-size: .8rem; font-weight: 400">({{ \App\Support\Money::symbol($currency).number_format($price / $credits, 2) }} each)</span>
                </div>
                @if ($canBuy && $canBuyOnline)
                    {{ ($this->buyAction)(['credits' => $credits]) }}
                @endif
            </x-filament::section>
        @endforeach
    </div>

    @if ($canBuy && ! $canBuyOnline)
        <x-filament::callout color="info" icon="heroicon-o-information-circle" heading="Need more credits?" description="Contact support and we'll add them to your account." />
    @endif

    {{ $this->table }}
</x-filament-panels::page>
