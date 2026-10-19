@extends('layouts.app', ['title' => 'Home'])

@section('content')
    <div class="empty-state">
        <h2>Welkom, {{ Auth::user()->name }}</h2>
        <p>{{ config('app.name') }} is in aanbouw.</p>
    </div>
@endsection
