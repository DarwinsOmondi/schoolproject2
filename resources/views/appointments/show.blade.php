@extends('layouts.app')

@section('title', 'Appointment')

@section('content')
    <header class="page-heading">
        <div class="page-heading-copy">
            <span class="page-eyebrow">CARE SCHEDULE</span>
            <h1>Appointment details</h1>
            <p>Review your consultation and the next step in your care.</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route($role->value.'.appointments.index') }}">← All appointments</a>
    </header>
    <section class="card mt-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                <h2 class="h5 mb-0" style="color: #3f516d;">Appointment #{{ $appointment->id }}</h2>
                <span class="badge text-bg-{{ $appointment->status === 'approved' ? 'success' : ($appointment->status === 'pending' ? 'warning' : ($appointment->status === 'cancelled' ? 'danger' : 'secondary')) }}">{{ ucfirst($appointment->status) }}</span>
            </div>
            <dl class="row mt-4">
                <dt class="col-sm-3">Patient</dt><dd class="col-sm-9">{{ $appointment->patient->user->name }}</dd>
                <dt class="col-sm-3">Doctor</dt><dd class="col-sm-9">{{ $appointment->doctor->user->name }} · {{ $appointment->doctor->specialty }}</dd>
                <dt class="col-sm-3">Scheduled</dt><dd class="col-sm-9">{{ $appointment->scheduled_at->format('M j, Y g:i A') }} {{ config('app.timezone') }}</dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9">{{ ucfirst($appointment->status) }}</dd>
                @if ($appointment->reason)
                    <dt class="col-sm-3">Reason</dt><dd class="col-sm-9">{{ $appointment->reason }}</dd>
                @endif
            </dl>
            <div class="d-flex flex-wrap gap-2">
                @if (in_array($appointment->status, ['approved', 'rescheduled', 'completed'], true))
                    <a class="btn btn-outline-primary" href="{{ route($role->value.'.appointments.messages.index', $appointment) }}">Open messages</a>
                @endif
                @if (in_array($appointment->status, ['approved', 'rescheduled'], true))
                    <a class="btn btn-primary" href="{{ route('appointments.video', $appointment) }}">Join video consultation</a>
                @endif
                @if (in_array($appointment->status, ['pending', 'approved', 'rescheduled'], true) && $appointment->scheduled_at->isFuture())
                    <form method="POST" action="{{ route($role->value.'.appointments.cancel', $appointment) }}" onsubmit="return confirm('Cancel this appointment?')">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-outline-danger" type="submit">Cancel appointment</button>
                    </form>
                @endif
            </div>
        </div>
    </section>
@endsection
