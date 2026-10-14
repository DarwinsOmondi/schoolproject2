@extends('layouts.app')

@section('title', 'Doctor dashboard')

@section('content')
    @php
    @endphp

    <section class="welcome-banner">
        <div class="welcome-content">
            <span class="page-eyebrow">DOCTOR WORKSPACE</span>
            <h1>Good {{ (int) $today->format('G') < 12 ? 'morning' : ((int) $today->format('G') < 17 ? 'afternoon' : 'evening') }}, {{ $user->name }}</h1>
            <p>{{ $doctor->specialty }} · {{ $doctor->is_approved ? 'Your clinical workspace is ready.' : 'Your profile is awaiting administrator approval.' }}</p>
            <span class="welcome-date">{{ $today->format('l, F j, Y') }} · All times {{ config('app.timezone') }}</span>
        </div>
        <div class="welcome-illustration" aria-hidden="true">✚</div>
    </section>

    @unless ($doctor->is_approved)
        <div class="alert app-alert app-alert-danger" role="status"><span aria-hidden="true">!</span><span>Your profile is awaiting administrator approval. Appointment and clinical tools will appear here after it is approved.</span></div>
    @endunless

    <div class="dashboard-stats">
        <article class="stat-card"><span class="stat-icon stat-icon-blue" aria-hidden="true">▦</span><div class="stat-copy"><span>Upcoming visits</span><strong>{{ $upcomingAppointmentsCount }}</strong></div></article>
        <article class="stat-card"><span class="stat-icon stat-icon-peach" aria-hidden="true">◷</span><div class="stat-copy"><span>Awaiting your review</span><strong>{{ $pendingAppointmentCount }}</strong></div></article>
        <article class="stat-card"><span class="stat-icon stat-icon-mint" aria-hidden="true">✚</span><div class="stat-copy"><span>Your specialty</span><strong style="font-size: 1rem; letter-spacing: -.025em;">{{ $doctor->specialty }}</strong></div></article>
    </div>

    <div class="dashboard-grid">
        <section class="card">
            <div class="surface-header">
                <div><h2>Appointments & consultations</h2><p>Your upcoming schedule and recent patient visits.</p></div>
                @if ($doctor->is_approved)
                    <a class="surface-header-action" href="{{ route('doctor.appointments.index') }}">View all <span aria-hidden="true">→</span></a>
                @endif
            </div>
            <div class="surface-content">
                @forelse ($appointments as $appointment)
                    <article class="appointment-item">
                        <div class="d-flex flex-wrap justify-content-between gap-3">
                            <div class="d-flex align-items-start gap-3">
                                <span class="avatar">{{ collect(explode(' ', $appointment->patient->user->name))->filter()->take(2)->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))->implode('') }}</span>
                                <div>
                                    <h3 class="appointment-person mb-1">{{ $appointment->patient->user->name }}</h3>
                                    <p class="appointment-meta mb-2">{{ $appointment->scheduled_at->format('D, M j · g:i A') }} {{ config('app.timezone') }}</p>
                                    @if ($appointment->reason)
                                        <p class="small text-secondary mb-2">{{ $appointment->reason }}</p>
                                    @endif
                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                        <span class="badge text-bg-{{ $appointment->status === 'approved' ? 'success' : ($appointment->status === 'pending' ? 'warning' : ($appointment->status === 'cancelled' ? 'danger' : 'secondary')) }}">{{ ucfirst($appointment->status) }}</span>
                                        @if (in_array($appointment->status, ['approved', 'rescheduled', 'completed'], true))
                                            <a class="surface-header-action" href="{{ route('doctor.appointments.messages.index', $appointment) }}">Messages ({{ $appointment->messages_count }})</a>
                                        @endif
                                        @if (in_array($appointment->status, ['approved', 'rescheduled'], true))
                                            <a class="surface-header-action" href="{{ route('appointments.video', $appointment) }}">Video consultation ↗</a>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            @if (! in_array($appointment->status, ['completed', 'cancelled'], true))
                                <div class="d-flex flex-wrap align-items-start gap-2">
                                    <form method="POST" action="{{ route('doctor.appointments.update', $appointment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="approved">
                                        <button class="btn btn-sm btn-success" type="submit">{{ $appointment->status === 'approved' ? 'Confirm visit' : 'Approve' }}</button>
                                    </form>
                                    <form class="d-flex flex-wrap gap-2 reschedule-form" method="POST" action="{{ route('doctor.appointments.update', $appointment) }}" data-availability-url="{{ route('doctor.appointments.available-slots', $appointment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="rescheduled">
                                        <input class="form-control form-control-sm reschedule-date" type="date" min="{{ $today->format('Y-m-d') }}" aria-label="New appointment date" required>
                                        <select class="form-select form-select-sm reschedule-slot" name="scheduled_at" required disabled aria-label="New appointment time"><option value="">Choose a date first</option></select>
                                        <button class="btn btn-sm btn-outline-primary" type="submit">Reschedule</button>
                                    </form>
                                    <form method="POST" action="{{ route('doctor.appointments.update', $appointment) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="status" value="completed">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Complete visit</button>
                                    </form>
                                    @if ($appointment->scheduled_at->isFuture())
                                        <form method="POST" action="{{ route('doctor.appointments.cancel', $appointment) }}" onsubmit="return confirm('Cancel this appointment?')">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>

                        @if ($appointment->status === 'completed')
                            <hr class="my-3">
                            <div class="row g-3">
                                <div class="col-12 col-lg-6">
                                    <h4 class="h6" style="color: #40536d;">Add clinical notes</h4>
                                    <form method="POST" action="{{ route('doctor.medical-records.store') }}">
                                        @csrf
                                        <input type="hidden" name="patient_id" value="{{ $appointment->patient_id }}">
                                        <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">
                                        <input type="hidden" name="record_type" value="consultation">
                                        <input class="form-control form-control-sm mb-2" name="title" placeholder="Note title" required maxlength="255">
                                        <textarea class="form-control form-control-sm mb-2" name="content" rows="3" placeholder="Clinical notes" required></textarea>
                                        <button class="btn btn-sm btn-primary" type="submit">Save clinical notes</button>
                                    </form>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <h4 class="h6" style="color: #40536d;">Issue a prescription</h4>
                                    <form method="POST" action="{{ route('doctor.prescriptions.store') }}">
                                        @csrf
                                        <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">
                                        <div class="row g-2">
                                            <div class="col-12"><input class="form-control form-control-sm" name="medication_name" placeholder="Medication" required maxlength="255"></div>
                                            <div class="col-6"><input class="form-control form-control-sm" name="dosage" placeholder="Dosage" required maxlength="255"></div>
                                            <div class="col-6"><input class="form-control form-control-sm" name="frequency" placeholder="Frequency" maxlength="255"></div>
                                            <div class="col-6"><input class="form-control form-control-sm" name="duration" placeholder="Duration" maxlength="255"></div>
                                            <div class="col-6"><input class="form-control form-control-sm" type="number" name="refills" placeholder="Refills" min="0" max="255" value="0"></div>
                                            <div class="col-12"><textarea class="form-control form-control-sm" name="instructions" rows="2" placeholder="Instructions"></textarea></div>
                                            <div class="col-12"><button class="btn btn-sm btn-primary" type="submit">Issue prescription</button></div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @endif
                    </article>
                @empty
                    <div class="empty-state">
                        <span class="empty-state-icon" aria-hidden="true">▦</span>
                        <strong>{{ $doctor->is_approved ? 'Your schedule is clear' : 'Your workspace is getting ready' }}</strong>
                        <p>{{ $doctor->is_approved ? 'When a patient requests a visit, you’ll see it here.' : 'We’ll let you know as soon as your profile is approved.' }}</p>
                    </div>
                @endforelse
            </div>
        </section>

        <aside class="dashboard-side-column">
            <section class="card">
                <div class="surface-header"><div><h2>Your practice</h2><p>A few quick links for your day.</p></div></div>
                <div class="surface-content">
                    <a class="quick-action-link" href="{{ route('profile.edit') }}"><span class="quick-action-icon" aria-hidden="true">◷</span><span>Set your weekly availability</span></a>
                    <a class="quick-action-link" href="{{ route('doctor.appointments.index') }}"><span class="quick-action-icon" aria-hidden="true">▦</span><span>Review appointment requests</span></a>
                    <a class="quick-action-link" href="{{ route('doctor.medical-records.index') }}"><span class="quick-action-icon" aria-hidden="true">▤</span><span>Browse clinical records</span></a>
                    <a class="quick-action-link" href="{{ route('doctor.prescriptions.index') }}"><span class="quick-action-icon" aria-hidden="true">✚</span><span>View prescriptions</span></a>
                </div>
            </section>
            <section class="card">
                <div class="surface-header"><div><h2>Profile status</h2><p>Your provider listing at a glance.</p></div></div>
                <div class="surface-content">
                    <div class="d-flex justify-content-between align-items-center gap-3 py-2"><span class="text-secondary">Review</span><span class="badge text-bg-{{ $doctor->is_approved ? 'success' : 'warning' }}">{{ $doctor->is_approved ? 'Approved' : 'Awaiting review' }}</span></div>
                    <div class="d-flex justify-content-between align-items-center gap-3 py-2 border-top"><span class="text-secondary">Accepting patients</span><span class="badge text-bg-{{ $doctor->is_accepting_patients ? 'success' : 'secondary' }}">{{ $doctor->is_accepting_patients ? 'Yes' : 'Paused' }}</span></div>
                    <a class="btn btn-outline-primary w-100 mt-3" href="{{ route('profile.edit') }}">Manage profile & hours</a>
                </div>
            </section>
        </aside>
    </div>

    <script>
        document.querySelectorAll('.reschedule-form').forEach((form) => {
            const dateInput = form.querySelector('.reschedule-date');
            const slotSelect = form.querySelector('.reschedule-slot');

            dateInput.addEventListener('change', async () => {
                slotSelect.replaceChildren(new Option('Loading available times…', ''));
                slotSelect.disabled = true;
                const url = new URL(form.dataset.availabilityUrl);
                url.searchParams.set('date', dateInput.value);

                try {
                    const response = await fetch(url, {headers: {'Accept': 'application/json'}});

                    if (!response.ok) {
                        slotSelect.replaceChildren(new Option('Could not load times', ''));
                        return;
                    }

                    const {slots, timezone} = await response.json();
                    slotSelect.replaceChildren(new Option(slots.length ? 'Choose an available time' : 'No times available', ''));

                    for (const slot of slots) {
                        const [hour, minute] = slot.slice(11).split(':').map(Number);
                        const label = new Date(Date.UTC(2000, 0, 1, hour, minute))
                            .toLocaleTimeString([], {hour: 'numeric', minute: '2-digit', timeZone: timezone});
                        slotSelect.add(new Option(label, slot));
                    }

                    slotSelect.disabled = slots.length === 0;
                } catch {
                    slotSelect.replaceChildren(new Option('Could not load times', ''));
                }
            });
        });
    </script>
@endsection
