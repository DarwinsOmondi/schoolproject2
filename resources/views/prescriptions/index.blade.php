@extends('layouts.app')

@section('title', 'Prescriptions')

@section('content')
    <header class="page-heading">
        <div class="page-heading-copy"><span class="page-eyebrow">MEDICATION & CARE</span><h1>Prescriptions</h1><p>Your digital prescriptions from completed consultations.</p></div>
    </header>
    <div class="row g-3">
        @forelse ($prescriptions as $prescription)
            <div class="col-12 col-md-6 col-xl-4">
                <article class="card h-100">
                    <div class="card-body">
                        <div class="small text-secondary mb-2">Issued {{ $prescription->created_at->format('M j, Y') }}</div>
                        <h2 class="h5">{{ $prescription->medication_name }}</h2>
                        <p class="mb-1">{{ $prescription->dosage }}</p>
                        <p class="text-secondary small">
                            {{ $prescription->frequency ?: 'Frequency not specified' }}
                            @if ($prescription->duration) · {{ $prescription->duration }} @endif
                        </p>
                        <p class="small">Prescribed by {{ $prescription->doctor->user->name }}</p>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route(auth()->user()->role->value.'.prescriptions.show', $prescription) }}">View</a>
                            <a class="btn btn-sm btn-primary" href="{{ route(auth()->user()->role->value.'.prescriptions.download', $prescription) }}">Download</a>
                        </div>
                    </div>
                </article>
            </div>
        @empty
            <div class="col-12"><section class="card"><div class="empty-state"><span class="empty-state-icon" aria-hidden="true">✚</span><strong>No prescriptions yet</strong><p>When your doctor issues a prescription, you’ll be able to view and save it here.</p></div></section></div>
        @endforelse
    </div>
    <div class="mt-3">{{ $prescriptions->links() }}</div>
@endsection
