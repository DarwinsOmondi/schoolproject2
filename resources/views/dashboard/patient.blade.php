@extends('layouts.app')

@section('title', 'Your overview')

@section('content')
    @php
    @endphp

    <section class="welcome-banner">
        <div class="welcome-content">
            <span class="page-eyebrow">YOUR HEALTH, ALL IN ONE PLACE</span>
            <h1>Hello, {{ $user->name }} <span aria-hidden="true">✳</span></h1>
            <p>A little more peace of mind starts here. Your care team, upcoming visits, and health information are all together.</p>
            <span class="welcome-date">{{ $today->format('l, F j, Y') }}</span>
        </div>
        <div class="welcome-illustration" aria-hidden="true">✚</div>
    </section>

    <div class="dashboard-stats">
        <article class="stat-card">
            <span class="stat-icon stat-icon-blue" aria-hidden="true">▦</span>
            <div class="stat-copy"><span>Upcoming visits</span><strong>{{ $upcomingAppointmentsCount }}</strong></div>
        </article>
        <article class="stat-card">
            <span class="stat-icon stat-icon-mint" aria-hidden="true">▤</span>
            <div class="stat-copy"><span>Health records</span><strong>{{ $medicalRecordCount }}</strong></div>
        </article>
        <article class="stat-card">
            <span class="stat-icon stat-icon-lilac" aria-hidden="true">✚</span>
            <div class="stat-copy"><span>Prescriptions</span><strong>{{ $prescriptionCount }}</strong></div>
        </article>
    </div>

    <div class="dashboard-grid">
        <div class="dashboard-main-column">
            <section class="card">
                <div class="surface-header">
                    <div><h2>Your appointments</h2><p>All the little steps in your care journey.</p></div>
                    <a class="surface-header-action" href="{{ route('patient.appointments.index') }}">View all <span aria-hidden="true">→</span></a>
                </div>

                @if ($doctors->isNotEmpty())
                    <div class="surface-content">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div><h3 class="h6 mb-1" style="color: #3b4f6c; font-weight: 680;">Find a time to talk</h3><p class="small mb-0 text-secondary">Choose an available 30-minute visit with your doctor.</p></div>
                            <span class="soft-pill">All times {{ config('app.timezone') }}</span>
                        </div>
                        <form class="row g-3 align-items-end" method="POST" action="{{ route('patient.appointments.store') }}">
                            @csrf
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="doctor_id">Choose a doctor</label>
                                <select class="form-select" id="doctor_id" name="doctor_id" required>
                                    @foreach ($doctors as $doctor)
                                        <option
                                            value="{{ $doctor->id }}"
                                            data-availability-url="{{ route('patient.doctors.availability', $doctor) }}"
                                            @selected(old('doctor_id') == $doctor->id)
                                        >
                                            {{ $doctor->user->name }} — {{ $doctor->specialty }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="appointment_date">Preferred date</label>
                                <input class="form-control" id="appointment_date" name="appointment_date" type="date" min="{{ $today->format('Y-m-d') }}" value="{{ old('appointment_date') }}" required>
                            </div>
                            <div class="col-12 col-md-7">
                                <label class="form-label" for="scheduled_at">Available time</label>
                                <select class="form-select" id="scheduled_at" name="scheduled_at" required disabled>
                                    <option value="">Select a doctor and date first</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-5">
                                <label class="form-label" for="reason">What would you like help with?</label>
                                <input class="form-control" id="reason" name="reason" value="{{ old('reason') }}" maxlength="2000" placeholder="Optional">
                            </div>
                            <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3">
                                <p id="availability-status" class="small text-secondary mb-0" role="status" aria-live="polite"></p>
                                <button class="btn btn-primary" type="submit">Request an appointment <span aria-hidden="true">→</span></button>
                            </div>
                        </form>
                    </div>
                @endif

                <div class="surface-content {{ $doctors->isNotEmpty() ? 'pt-0' : '' }}">
                    @forelse ($appointments->take(6) as $appointment)
                        <article class="appointment-item">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="avatar">{{ collect(explode(' ', $appointment->doctor->user->name))->filter()->take(2)->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))->implode('') }}</span>
                                    <div>
                                        <div class="appointment-person">{{ $appointment->doctor->user->name }}</div>
                                        <div class="appointment-meta">{{ $appointment->scheduled_at->format('D, M j · g:i A') }} {{ config('app.timezone') }}</div>
                                        @if ($appointment->reason)
                                            <div class="small text-secondary mt-1">{{ $appointment->reason }}</div>
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="badge text-bg-{{ $appointment->status === 'approved' ? 'success' : ($appointment->status === 'pending' ? 'warning' : ($appointment->status === 'cancelled' ? 'danger' : 'secondary')) }}">{{ ucfirst($appointment->status) }}</span>
                                    @if (in_array($appointment->status, ['approved', 'rescheduled'], true))
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('appointments.video', $appointment) }}">Join visit</a>
                                    @endif
                                    @if (in_array($appointment->status, ['approved', 'rescheduled', 'completed'], true))
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('patient.appointments.messages.index', $appointment) }}">Messages <span class="opacity-75">({{ $appointment->messages_count }})</span></a>
                                    @endif
                                    @if (in_array($appointment->status, ['pending', 'approved', 'rescheduled'], true) && $appointment->scheduled_at->isFuture())
                                        <form method="POST" action="{{ route('patient.appointments.cancel', $appointment) }}" onsubmit="return confirm('Cancel this appointment?')">
                                            @csrf
                                            @method('PATCH')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">
                            <span class="empty-state-icon" aria-hidden="true">▦</span>
                            <strong>Your next chapter of care starts here</strong>
                            <p>When an appointment is booked, you’ll see its details here.</p>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="dashboard-side-column">
            <section class="card">
                <div class="surface-header">
                    <div><h2>Health records</h2><p>Your recent clinical notes.</p></div>
                    <a class="surface-header-action" href="{{ route('patient.medical-records.index') }}">View</a>
                </div>
                <div class="list-group list-group-flush">
                    @forelse ($medicalRecords as $record)
                        <a class="list-group-item list-group-item-action py-3" href="{{ route('patient.medical-records.show', $record) }}">
                            <strong class="d-block" style="color: #50617a; font-size: .82rem;">{{ $record->title }}</strong>
                            <span class="small text-secondary">{{ ucfirst(str_replace('_', ' ', $record->record_type)) }} · {{ $record->created_at->format('M j') }}</span>
                        </a>
                    @empty
                        <div class="empty-state"><span class="empty-state-icon" aria-hidden="true">▤</span><strong>No records yet</strong><p>Your doctor’s notes will appear here.</p></div>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="surface-header">
                    <div><h2>Prescriptions</h2><p>Medication from your care team.</p></div>
                    <a class="surface-header-action" href="{{ route('patient.prescriptions.index') }}">View</a>
                </div>
                <div class="surface-content">
                    @forelse ($prescriptions as $prescription)
                        <article class="d-flex justify-content-between align-items-center gap-3 py-2 {{ ! $loop->last ? 'border-bottom' : '' }}">
                            <div class="d-flex align-items-center gap-3">
                                <span class="stat-icon stat-icon-mint" style="width: 36px; height: 36px; border-radius: 11px; font-size: 1rem;" aria-hidden="true">✚</span>
                                <div>
                                    <strong class="d-block" style="color: #50617a; font-size: .82rem;">{{ $prescription->medication_name }}</strong>
                                    <span class="small text-secondary">{{ $prescription->dosage }}</span>
                                </div>
                            </div>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('patient.prescriptions.download', $prescription) }}">Save</a>
                        </article>
                    @empty
                        <div class="empty-state"><span class="empty-state-icon" aria-hidden="true">✚</span><strong>Nothing to pick up</strong><p>Your prescriptions will appear here when issued.</p></div>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>

    @if ($doctors->isNotEmpty())
        <script>
            const doctorSelect = document.getElementById('doctor_id');
            const dateInput = document.getElementById('appointment_date');
            const slotSelect = document.getElementById('scheduled_at');
            const availabilityStatus = document.getElementById('availability-status');
            const scheduleTimezone = @js(config('app.timezone'));
            const previousSlot = @js(old('scheduled_at'));

            async function loadAppointmentSlots() {
                slotSelect.replaceChildren(new Option('Loading available times…', ''));
                slotSelect.disabled = true;
                availabilityStatus.textContent = '';

                if (!doctorSelect.value || !dateInput.value) {
                    slotSelect.replaceChildren(new Option('Select a doctor and date first', ''));
                    return;
                }

                const selectedDoctor = doctorSelect.selectedOptions[0];
                const url = new URL(selectedDoctor.dataset.availabilityUrl);
                url.searchParams.set('date', dateInput.value);

                try {
                    const response = await fetch(url, {headers: {'Accept': 'application/json'}});

                    if (!response.ok) {
                        availabilityStatus.textContent = 'Could not load appointment times. Please try again.';
                        slotSelect.replaceChildren(new Option('No times loaded', ''));
                        return;
                    }

                    const {slots} = await response.json();
                    slotSelect.replaceChildren(new Option(
                        slots.length ? 'Choose a time that works for you' : 'No times available',
                        '',
                    ));

                    for (const slot of slots) {
                        const [hour, minute] = slot.slice(11).split(':').map(Number);
                        const label = new Date(Date.UTC(2000, 0, 1, hour, minute))
                            .toLocaleTimeString([], {hour: 'numeric', minute: '2-digit', timeZone: scheduleTimezone});
                        slotSelect.add(new Option(label, slot, false, slot === previousSlot));
                    }

                    slotSelect.disabled = slots.length === 0;

                    if (!slots.length) {
                        availabilityStatus.textContent = 'There are no open visits for that date. Try another day.';
                    }
                } catch {
                    availabilityStatus.textContent = 'Could not load appointment times. Check your connection and try again.';
                    slotSelect.replaceChildren(new Option('No times loaded', ''));
                }
            }

            doctorSelect.addEventListener('change', loadAppointmentSlots);
            dateInput.addEventListener('change', loadAppointmentSlots);

            if (dateInput.value) {
                loadAppointmentSlots();
            }
        </script>
    @endif
@endsection
