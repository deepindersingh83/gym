<x-filament-panels::page>
    <x-filament::section>
        <form wire:submit="checkIn" style="display: flex; gap: .75rem; align-items: center">
            <x-filament::input.wrapper style="flex: 1">
                <x-filament::input
                    type="text"
                    wire:model="code"
                    placeholder="Scan the member's QR code, or type a member ID or phone number"
                    autofocus
                    autocomplete="off"
                    x-init="$el.focus()"
                    x-on:check-in-done.window="$el.focus()"
                />
            </x-filament::input.wrapper>
            <x-filament::button type="submit" icon="heroicon-o-arrow-right-end-on-rectangle">Check in</x-filament::button>
        </form>
    </x-filament::section>

    @if ($result)
        <x-filament::section>
            <div style="display: flex; gap: 1.25rem; align-items: center">
                @isset($result['name'])
                    <img
                        src="{{ $result['photo'] ?? 'https://ui-avatars.com/api/?size=160&name=' . urlencode($result['name']) }}"
                        alt=""
                        style="width: 96px; height: 96px; border-radius: 9999px; object-fit: cover"
                    >
                @endisset
                <div style="display: grid; gap: .35rem">
                    <x-filament::badge :color="$result['ok'] ? 'success' : 'danger'" size="lg">
                        {{ $result['message'] }}
                    </x-filament::badge>
                    @isset($result['name'])
                        <div style="font-size: 1.5rem; font-weight: 700">{{ $result['name'] }} <span style="opacity: .6; font-weight: 400">{{ $result['code'] }}</span></div>
                        <div>
                            {{ $result['plan'] ?? 'No plan' }}
                            @if ($result['end_date'])
                                · valid until {{ $result['end_date'] }} ({{ $result['days_left'] }} days left)
                            @endif
                        </div>
                        @if ($result['due'] > 0)
                            <x-filament::badge color="warning">Pending dues: {{ \App\Support\Money::format($result['due']) }}</x-filament::badge>
                        @endif
                    @endisset
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
