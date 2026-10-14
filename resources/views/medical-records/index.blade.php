@extends('layouts.app')

@section('title', 'Medical records')

@section('content')
    <header class="page-heading">
        <div class="page-heading-copy"><span class="page-eyebrow">YOUR HEALTH HISTORY</span><h1>Medical records</h1><p>Private clinical history and consultation notes.</p></div>
    </header>
    <section class="card">
        <div class="surface-header"><div><h2>Clinical history</h2><p>Only you and your authorized care team can view these records.</p></div></div>
        <div class="list-group list-group-flush">
            @forelse ($records as $record)
                <a class="list-group-item list-group-item-action py-3" href="{{ route(auth()->user()->role->value.'.medical-records.show', $record) }}">
                    <div class="d-flex justify-content-between gap-2">
                        <div>
                            <strong>{{ $record->title }}</strong>
                            <span class="d-block small text-secondary">{{ ucfirst(str_replace('_', ' ', $record->record_type)) }} · {{ $record->doctor->user->name }}</span>
                        </div>
                        <time class="small text-secondary">{{ $record->created_at->format('M j, Y') }}</time>
                    </div>
                </a>
            @empty
                <div class="empty-state"><span class="empty-state-icon" aria-hidden="true">▤</span><strong>No records yet</strong><p>Your clinical notes will be available here after your appointments.</p></div>
            @endforelse
        </div>
        <div class="card-body">{{ $records->links() }}</div>
    </section>
@endsection
