@extends('layouts.app')

@section('title', 'Appointments')

@section('content')
    <header class="page-heading">
        <div class="page-heading-copy">
            <span class="page-eyebrow">CARE SCHEDULE</span>
            <h1>Appointments</h1>
            <p>Your scheduled and requested consultations.</p>
        </div>
        <a class="btn btn-outline-primary" href="{{ route($role->value.'.dashboard') }}">← Back to overview</a>
    </header>
    <section class="card">
        <div class="surface-header">
            <div><h2>Your visits</h2><p>Scheduled in {{ config('app.timezone') }}.</p></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>{{ $role->value === 'patient' ? 'Doctor' : 'Patient' }}</th>
                        <th>When</th><th>Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($appointments as $appointment)
                    <tr>
                        <td>{{ $role->value === 'patient' ? $appointment->doctor->user->name : $appointment->patient->user->name }}</td>
                        <td>{{ $appointment->scheduled_at->format('M j, Y g:i A') }} {{ config('app.timezone') }}</td>
                        <td>{{ ucfirst($appointment->status) }}</td>
                        <td class="d-flex flex-wrap gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route($role->value.'.appointments.show', $appointment) }}">Details</a>
                            @if (in_array($appointment->status, ['approved', 'rescheduled'], true))
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('appointments.video', $appointment) }}">Join visit</a>
                            @endif
                            @if (in_array($appointment->status, ['approved', 'rescheduled', 'completed'], true))
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route($role->value.'.appointments.messages.index', $appointment) }}">Messages</a>
                            @endif
                            @if (in_array($appointment->status, ['pending', 'approved', 'rescheduled'], true) && $appointment->scheduled_at->isFuture())
                                <form method="POST" action="{{ route($role->value.'.appointments.cancel', $appointment) }}" onsubmit="return confirm('Cancel this appointment?')">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-secondary text-center py-4">No appointments found.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $appointments->links() }}</div>
    </section>
@endsection
