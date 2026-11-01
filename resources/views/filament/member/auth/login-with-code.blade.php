<x-filament-panels::page.simple>
    <form wire:submit="{{ $codeSent ? 'verify' : 'sendCode' }}" style="display: grid; gap: 1.25rem">
        {{ $this->form }}

        <x-filament::button type="submit" style="width: 100%">
            {{ $codeSent ? 'Sign in' : 'Send code' }}
        </x-filament::button>

        <div style="display: flex; justify-content: space-between; font-size: .875rem; gap: 1rem; flex-wrap: wrap">
            @if ($codeSent)
                <x-filament::link tag="button" type="button" wire:click="startOver">Use a different number / resend</x-filament::link>
            @endif
            <x-filament::link :href="filament()->getLoginUrl()">Sign in with a password</x-filament::link>
        </div>
    </form>
</x-filament-panels::page.simple>
