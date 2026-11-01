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
                            @if ($gym->isGstRegistered() && $plan->gst_rate > 0)<span class="muted"> {{ $gym->country === 'AU' ? 'incl. GST' : '+ GST' }}</span>@endif
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

            <form method="POST" action="{{ route('join.store', $gym, false) }}" enctype="multipart/form-data">
                @csrf
                <div class="grid">
                    @foreach ([
                        ['name', 'Full name', 'text', true],
                        ['phone', 'Mobile number', 'tel', true],
                        ['email', 'Email (optional)', 'email', false],
                        ['password', 'Choose a password for the member app', 'password', true],
                        ['date_of_birth', 'Date of birth', 'date', false],
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
                    @if ($heightInFeet)
                        <div>
                            <label for="height_feet">Height (feet and inches)</label>
                            <div style="display: flex; gap: 8px">
                                <input id="height_feet" name="height_feet" type="number" min="1" max="8" step="1" placeholder="ft" value="{{ old('height_feet') }}" aria-label="Feet">
                                <input id="height_inches" name="height_inches" type="number" min="0" max="11.9" step="0.1" placeholder="in" value="{{ old('height_inches') }}" aria-label="Inches">
                            </div>
                            @error('height_feet')<div class="error">{{ $message }}</div>@enderror
                            @error('height_inches')<div class="error">{{ $message }}</div>@enderror
                        </div>
                    @else
                        <div>
                            <label for="height_cm">Height (cm)</label>
                            <input id="height_cm" name="height_cm" type="number" step="0.1" value="{{ old('height_cm') }}">
                            @error('height_cm')<div class="error">{{ $message }}</div>@enderror
                        </div>
                    @endif
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
                        <label>Photo</label>
                        <div class="photo-box">
                            <div class="photo-frame">
                                <video id="photo-video" playsinline muted hidden></video>
                                <img id="photo-preview" alt="" hidden>
                                <span id="photo-empty">📷</span>
                            </div>
                            <div class="photo-actions">
                                <button type="button" class="btn secondary" id="photo-take">Take photo</button>
                                <button type="button" class="btn" id="photo-capture" hidden>Capture</button>
                                <button type="button" class="btn secondary" id="photo-upload">Upload</button>
                            </div>
                        </div>
                        <input type="hidden" name="photo_data" id="photo-data">
                        <input type="file" id="photo-file" accept="image/*" hidden>
                        <input type="file" id="photo-camera" accept="image/*" capture="user" hidden>
                        <div class="error" id="photo-error" hidden></div>
                        @error('photo_data')<div class="error">{{ $message }}</div>@enderror
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

@push('scripts')
<script>
(() => {
    const $ = (id) => document.getElementById(id);
    const video = $('photo-video'), preview = $('photo-preview'), empty = $('photo-empty');
    let stream = null;

    // Resize to at most 1000px and send as JPEG; the server re-checks and re-encodes it.
    const toJpeg = (source, w, h) => {
        const scale = Math.min(1, 1000 / Math.max(w, h));
        const canvas = Object.assign(document.createElement('canvas'), { width: Math.round(w * scale), height: Math.round(h * scale) });
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', 0.85);
    };
    const show = (dataUrl) => {
        $('photo-data').value = dataUrl;
        preview.src = dataUrl; preview.hidden = false; empty.hidden = true; video.hidden = true;
        $('photo-capture').hidden = true; $('photo-take').hidden = false;
    };
    const stop = () => { stream?.getTracks().forEach((t) => t.stop()); stream = null; };
    const error = (text) => { $('photo-error').textContent = text; $('photo-error').hidden = ! text; };
    const fromFile = (input) => {
        const file = input.files?.[0]; input.value = '';
        if (! file) return;
        const img = new Image();
        img.onload = () => { show(toJpeg(img, img.naturalWidth, img.naturalHeight)); URL.revokeObjectURL(img.src); };
        img.onerror = () => error('That image could not be opened.');
        img.src = URL.createObjectURL(file);
    };

    $('photo-take').addEventListener('click', async () => {
        error('');
        if (! navigator.mediaDevices?.getUserMedia) { $('photo-camera').click(); return; }
        try {
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' }, audio: false });
            video.srcObject = stream; await video.play();
            video.hidden = false; preview.hidden = true; empty.hidden = true;
            $('photo-capture').hidden = false; $('photo-take').hidden = true;
        } catch (e) {
            $('photo-camera').click();
        }
    });
    $('photo-capture').addEventListener('click', () => { show(toJpeg(video, video.videoWidth, video.videoHeight)); stop(); });
    $('photo-upload').addEventListener('click', () => $('photo-file').click());
    $('photo-file').addEventListener('change', (e) => fromFile(e.target));
    $('photo-camera').addEventListener('change', (e) => fromFile(e.target));
    window.addEventListener('pagehide', stop);
})();
</script>
@endpush
