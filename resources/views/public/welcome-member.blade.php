@extends('public.layout', ['brand' => $gym->primary_color])

@section('title', 'Welcome to '.$gym->name)

@section('content')
    <div class="card">
        <h1>Welcome to {{ $gym->name }}!</h1>
        <p>Your details are saved. Visit the front desk to start your membership, or open the member app to see your membership, payments and progress.</p>
        <p><a class="btn" href="{{ route('filament.member.pages.dashboard') }}">Open member app</a></p>
    </div>
@endsection
