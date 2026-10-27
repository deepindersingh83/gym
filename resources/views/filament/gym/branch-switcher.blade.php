@php
    $gym = \App\Support\Tenancy::gym();
    $branches = $gym && \App\Support\Tenancy::canSwitchBranch()
        ? \App\Models\Branch::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')
        : collect();
    $selected = \App\Support\Tenancy::branchId();
@endphp

@if ($branches->count() > 1)
    <form method="POST" action="{{ route('branch.select') }}" style="min-width: 11rem">
        @csrf
        <input type="hidden" name="gym_id" value="{{ $gym->getKey() }}">
        <x-filament::input.wrapper>
            <x-filament::input.select name="branch_id" aria-label="Branch" onchange="this.form.submit()">
                <option value="">All branches</option>
                @foreach ($branches as $id => $name)
                    <option value="{{ $id }}" @selected($selected === $id)>{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </form>
@endif
