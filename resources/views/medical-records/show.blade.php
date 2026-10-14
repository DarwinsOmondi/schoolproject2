@extends('layouts.app')

@section('title', 'Medical record')

@section('content')
    <header class="page-heading">
        <div class="page-heading-copy"><span class="page-eyebrow">YOUR HEALTH HISTORY</span><h1>Clinical note</h1><p>Private information from your care team.</p></div>
        <a class="btn btn-outline-secondary" href="{{ route(auth()->user()->role->value.'.medical-records.index') }}">← Medical records</a>
    </header>
    <article class="card mt-3">
        <div class="card-body p-4">
            <p class="text-secondary mb-1">{{ ucfirst(str_replace('_', ' ', $record->record_type)) }} · {{ $record->created_at->format('F j, Y') }}</p>
            <h1 class="h3">{{ $record->title }}</h1>
            <p class="small text-secondary">Recorded by {{ $record->doctor->user->name }}</p>
            <hr>
            <div style="white-space: pre-wrap;">{{ $record->content }}</div>
        </div>
    </article>
@endsection
