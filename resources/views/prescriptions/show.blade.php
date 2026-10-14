@extends('layouts.app')

@section('title', 'Prescription details')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <header class="page-heading">
                <div class="page-heading-copy"><span class="page-eyebrow">MEDICATION & CARE</span><h1>Prescription details</h1><p>Your digital prescription and directions.</p></div>
                <a class="btn btn-outline-secondary" href="{{ route(auth()->user()->role->value.'.prescriptions.index') }}">← Prescriptions</a>
            </header>
            <h1 class="h3 mt-2 mb-0">Prescription details</h1>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print</button>
            <a class="btn btn-primary" href="{{ route(auth()->user()->role->value.'.prescriptions.download', $prescription) }}">Download</a>
        </div>
    </div>
    <article class="card">
        <div class="card-body p-4">
            <p class="text-secondary">Issued {{ $prescription->created_at->format('F j, Y') }}</p>
            <h2 class="h4">{{ $prescription->medication_name }}</h2>
            <dl class="row mt-4">
                <dt class="col-sm-3">Patient</dt><dd class="col-sm-9">{{ $prescription->patient->user->name }}</dd>
                <dt class="col-sm-3">Prescribing doctor</dt><dd class="col-sm-9">{{ $prescription->doctor->user->name }}</dd>
                <dt class="col-sm-3">Dosage</dt><dd class="col-sm-9">{{ $prescription->dosage }}</dd>
                <dt class="col-sm-3">Frequency</dt><dd class="col-sm-9">{{ $prescription->frequency ?: 'Not specified' }}</dd>
                <dt class="col-sm-3">Duration</dt><dd class="col-sm-9">{{ $prescription->duration ?: 'Not specified' }}</dd>
                <dt class="col-sm-3">Refills</dt><dd class="col-sm-9">{{ $prescription->refills }}</dd>
                <dt class="col-sm-3">Instructions</dt><dd class="col-sm-9">{{ $prescription->instructions ?: 'None' }}</dd>
            </dl>
        </div>
    </article>
@endsection
