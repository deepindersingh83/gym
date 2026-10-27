@extends('public.layout', ['brand' => $gym->primary_color])

@section('title', 'Join '.$gym->name)

@section('content')
    <div class="card hero">
        @if ($gym->logo_url)<img src="{{ $gym->logo_url }}" alt="{{ $gym->name }} logo">@endif
        <div>
            <h1>{{ $gym->name }}</h1>
            <div class="muted">{{ collect([$gym->address, $gym->city])->filter()->implode(', ') }}</div>
            <div class="muted">{{ collect([$gym->phone, $gym->email])->filter()->implode(' · ') }}</div>
        </div>
    </div>

    <div class="grid">
        @if ($gym->opening_hours)
            <div class="card">
                <h2>Opening hours</h2>
                <table class="hours">
                    @foreach ($gym->opening_hours as $row)
                        <tr>
                            <td>{{ ucfirst($row['day']) }}</td>
                            <td>{{ ($row['closed'] ?? false) ? 'Closed' : \Illuminate\Support\Carbon::parse($row['opens'])->format('g:i A').' – '.\Illuminate\Support\Carbon::parse($row['closes'])->format('g:i A') }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        @if ($plans->isNotEmpty())
            <div class="card">
                <h2>Membership plans</h2>
                <div style="display: grid; gap: 10px">
                    @foreach ($plans as $plan)
                        <div class="plan">
                            <div>{{ $plan->name }} <span class="muted">· {{ $plan->duration_label }}</span></div>
                            <strong>{{ \App\Support\Money::format($plan->price, $gym->currency) }}</strong>
                            @if ($gym->isGstRegistered() && $plan->gst_rate > 0)<span class="muted"> + GST</span>@endif
                            @if ($plan->description)<div class="muted">{{ $plan->description }}</div>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="card">
        <h2>Sign up</h2>

        @if (! $open)
            <p class="muted">Online signup is not available right now. Please visit or call the gym.</p>
        @else
            @if ($errors->any())
                <div class="alert">Please fix the highlighted fields below.</div>
            @endif

            <form method="POST" action="{{ route('join.store', $gym) }}" enctype="multipart/form-data">
                @csrf
                <div class="grid">
                    @foreach ([
                        ['name', 'Full name', 'text', true],
                        ['phone', 'Mobile number', 'tel', true],
                        ['email', 'Email', 'email', true],
                        ['password', 'Choose a password for the member app', 'password', true],
                        ['date_of_birth', 'Date of birth', 'date', false],
                        ['height_cm', 'Height (cm)', 'number', false],
                        ['weight_kg', 'Weight (kg)', 'number', false],
                        ['emergency_contact_name', 'Emergency contact name', 'text', false],
                        ['emergency_contact_phone', 'Emergency contact phone', 'tel', false],
                    ] as [$field, $label, $type, $required])
                        <div>
                            <label for="{{ $field }}">{{ $label }}@if ($required) *@endif</label>
                            <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" @if ($type !== 'password') value="{{ old($field) }}" @endif @required($required) @if ($type === 'number') step="0.1" @endif>
                            @error($field)<div class="error">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                    <div>
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">—</option>
                            @foreach (['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('gender') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="goal">Fitness goal</label>
                        <select id="goal" name="goal">
                            <option value="">—</option>
                            @foreach ($goals as $value => $label)
                                <option value="{{ $value }}" @selected(old('goal') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="photo">Photo</label>
                        <input id="photo" name="photo" type="file" accept="image/*">
                        @error('photo')<div class="error">{{ $message }}</div>@enderror
                    </div>
                    @if ($canPayOnline && $plans->isNotEmpty())
                        <div>
                            <label for="plan_id">Pay online for a plan now (optional)</label>
                            <select id="plan_id" name="plan_id">
                                <option value="">Pay later at the gym</option>
                                @foreach ($plans as $plan)
                                    <option value="{{ $plan->id }}" @selected((string) old('plan_id') === (string) $plan->id)>{{ $plan->name }} — {{ \App\Support\Money::format($plan->price, $gym->currency) }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <h2 style="margin-top: 24px">Health questionnaire</h2>
                <p class="muted">Tick any that apply, so your trainer can plan safely.</p>
                <div class="grid">
                    @foreach ($healthQuestions as $key => $question)
                        <label class="check"><input type="checkbox" name="health_questionnaire[]" value="{{ $key }}" @checked(in_array($key, old('health_questionnaire', [])))> {{ $question }}</label>
                    @endforeach
                </div>
                <div style="margin-top: 12px">
                    <label for="medical_conditions">Anything else we should know (injuries, conditions)?</label>
                    <textarea id="medical_conditions" name="medical_conditions" rows="2">{{ old('medical_conditions') }}</textarea>
                </div>

                <label class="check" style="margin-top: 16px">
                    <input type="checkbox" name="consent" value="1" required>
                    I agree that {{ $gym->name }} may store my details, photo and health information to manage my membership and contact me about it.
                </label>
                @error('consent')<div class="error">{{ $message }}</div>@enderror

                <p style="margin-top: 20px"><button class="btn" type="submit">Join {{ $gym->name }}</button></p>
            </form>
        @endif
    </div>
@endsection
