<x-filament-panels::page>
    <x-filament::section>
        <div style="text-align: center; display: grid; gap: .6rem">
            <div style="font-size: 2.5rem">🎁</div>
            <div style="font-size: 1.25rem; font-weight: 800">Bring a friend to {{ $gym->name }}</div>
            <div style="opacity: .8">
                When your friend joins with your link and pays for their first membership,
                @if ($referrerDays > 0) you get <strong>{{ $referrerDays }} free days</strong>@endif
                @if ($referrerDays > 0 && $friendDays > 0) and @endif
                @if ($friendDays > 0) they get <strong>{{ $friendDays }} free days</strong>@endif.
            </div>
            <div x-data="{ copied: false, link: @js($link) }" style="display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center; margin-top: .5rem">
                <x-filament::button tag="a" :href="$whatsapp" target="_blank" icon="heroicon-o-chat-bubble-left-right" color="success">Share on WhatsApp</x-filament::button>
                <x-filament::button color="gray" icon="heroicon-o-clipboard" x-on:click="navigator.clipboard.writeText(link); copied = true; setTimeout(() => copied = false, 2000)">
                    <span x-text="copied ? 'Copied!' : 'Copy link'">Copy link</span>
                </x-filament::button>
            </div>
            <div style="font-size: .85rem; opacity: .7">Or tell them your code at the front desk: <strong style="letter-spacing: .05em">{{ $code }}</strong></div>
            @if ($bonusDays > 0)
                <div style="font-size: .9rem; color: rgb(22 163 74)">{{ $bonusDays }} free days are saved for your next membership.</div>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section heading="Your friends">
        @forelse ($referrals as $r)
            <div style="display: flex; justify-content: space-between; padding: .5rem 0; border-top: 1px solid rgba(127,127,127,.15); font-size: .9rem">
                <span>{{ $r['name'] }} <span style="opacity: .6">· joined {{ $r['joined']->format('d M Y') }}</span></span>
                @if ($r['rewarded'])
                    <x-filament::badge color="success">+{{ $r['days'] }} days</x-filament::badge>
                @else
                    <x-filament::badge color="gray">Waiting for first payment</x-filament::badge>
                @endif
            </div>
        @empty
            <div style="opacity: .6; font-size: .9rem">No friends yet. Share your link to start earning free days.</div>
        @endforelse
    </x-filament::section>
</x-filament-panels::page>
