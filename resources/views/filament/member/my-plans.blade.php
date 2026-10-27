<x-filament-panels::page>
    @forelse ($workouts as $plan)
        <x-filament::section :heading="$plan->title" :description="'Workout plan' . ($plan->trainer ? ' by ' . $plan->trainer->name : '')">
            <table style="width: 100%; border-collapse: collapse">
                <thead>
                    <tr style="text-align: left; opacity: .7"><th>Day</th><th>Exercise</th><th>Sets</th><th>Reps</th><th>Rest</th></tr>
                </thead>
                <tbody>
                    @foreach (collect($plan->exercises)->sortBy(fn ($e) => array_search($e['day'] ?? 'any', array_keys($days))) as $exercise)
                        <tr style="border-top: 1px solid rgba(127,127,127,.2)">
                            <td style="padding: .4rem 0">{{ $days[$exercise['day'] ?? 'any'] ?? '' }}</td>
                            <td>{{ $exercise['exercise'] ?? '' }}</td>
                            <td>{{ $exercise['sets'] ?? '' }}</td>
                            <td>{{ $exercise['reps'] ?? '' }}</td>
                            <td>{{ $exercise['rest'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($plan->notes)<p style="margin-top: .75rem; opacity: .8">{{ $plan->notes }}</p>@endif
        </x-filament::section>
    @empty
        <x-filament::section heading="Workout plan" description="Your trainer has not assigned a workout plan yet." />
    @endforelse

    @foreach ($diets as $plan)
        <x-filament::section :heading="$plan->title" :description="'Diet plan' . ($plan->daily_calories ? ' · ' . $plan->daily_calories . ' kcal/day' : '')">
            <table style="width: 100%; border-collapse: collapse">
                <thead><tr style="text-align: left; opacity: .7"><th>Time</th><th>Meal</th><th>What to eat</th><th>kcal</th></tr></thead>
                <tbody>
                    @foreach ($plan->meals ?? [] as $meal)
                        <tr style="border-top: 1px solid rgba(127,127,127,.2)">
                            <td style="padding: .4rem 0">{{ $meal['time'] ? \Illuminate\Support\Carbon::parse($meal['time'])->format('g:i A') : '' }}</td>
                            <td>{{ $meal['meal'] ?? '' }}</td>
                            <td>{{ $meal['items'] ?? '' }}</td>
                            <td>{{ $meal['calories'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            @if ($plan->notes)<p style="margin-top: .75rem; opacity: .8">{{ $plan->notes }}</p>@endif
        </x-filament::section>
    @endforeach
</x-filament-panels::page>
