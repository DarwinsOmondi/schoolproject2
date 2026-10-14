@extends('layouts.app')

@section('title', 'Administrator dashboard')

@section('content')
    <section class="welcome-banner">
        <div class="welcome-content">
            <span class="page-eyebrow">ADMINISTRATOR WORKSPACE</span>
            <h1>Care operations, at a glance.</h1>
            <p>Welcome back, {{ $user->name }}. Keep profiles moving and your care community in good hands.</p>
            <span class="welcome-date">{{ now()->format('l, F j, Y') }}</span>
        </div>
        <div class="welcome-illustration" aria-hidden="true">✳</div>
    </section>

    <div class="dashboard-stats">
        <article class="stat-card"><span class="stat-icon stat-icon-blue" aria-hidden="true">♧</span><div class="stat-copy"><span>Total accounts</span><strong>{{ $users->count() }}</strong></div></article>
        <article class="stat-card"><span class="stat-icon stat-icon-mint" aria-hidden="true">✚</span><div class="stat-copy"><span>Patients</span><strong>{{ $patientsCount }}</strong></div></article>
        <article class="stat-card"><span class="stat-icon stat-icon-lilac" aria-hidden="true">◷</span><div class="stat-copy"><span>Doctor profiles</span><strong>{{ $doctorsCount }}</strong></div></article>
    </div>

    @if ($doctorsAwaitingApproval->isNotEmpty())
        <section class="card mb-4">
            <div class="surface-header">
                <div><h2>Profiles awaiting review</h2><p>Review a provider’s credentials before they see patients.</p></div>
                <span class="badge text-bg-warning">{{ $doctorsAwaitingApproval->count() }} to review</span>
            </div>
            <div class="surface-content">
                @foreach ($doctorsAwaitingApproval as $doctor)
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 py-2 border-bottom">
                    <div class="d-flex align-items-center gap-3">
                        <span class="avatar">{{ collect(explode(' ', $doctor->user->name))->filter()->take(2)->map(fn (string $part): string => strtoupper(substr($part, 0, 1)))->implode('') }}</span>
                        <div>
                            <strong style="color: #41536d;">{{ $doctor->user->name }}</strong>
                            <span class="d-block small text-secondary">{{ $doctor->specialty }} · {{ $doctor->user->email }}</span>
                            <span class="small text-secondary">License {{ $doctor->license_number }}</span>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.doctors.approve', $doctor) }}">
                        @csrf
                        @method('PATCH')
                        <button class="btn btn-sm btn-success" type="submit">Approve doctor profile</button>
                    </form>
                </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="row g-4">
        <div class="col-12 col-xl-5">
            <section class="card">
                <div class="surface-header"><div><h2>Create an account</h2><p>Invite a patient, doctor, or administrator.</p></div></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.users.store') }}">
                        @csrf
                        <div class="mb-3">
                            <label class="form-label" for="name">Full name</label>
                            <input class="form-control" id="name" name="name" value="{{ old('name') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="user-email">Email</label>
                            <input class="form-control" id="user-email" type="email" name="email" value="{{ old('email') }}" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="user-password">Temporary password</label>
                            <input class="form-control" id="user-password" type="password" name="password" minlength="12" required>
                            <div class="form-text">Use at least 12 characters.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="role">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="patient" @selected(old('role', 'patient') === 'patient')>Patient</option>
                                <option value="doctor" @selected(old('role') === 'doctor')>Doctor</option>
                                <option value="admin" @selected(old('role') === 'admin')>Administrator</option>
                            </select>
                        </div>
                        <hr class="my-4">
                        <h3 class="h6" style="color: #50617a;">Patient details <span class="text-secondary fw-normal">(for patients)</span></h3>
                        <div class="mb-3">
                            <label class="form-label" for="date_of_birth">Date of birth</label>
                            <input class="form-control" id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="phone">Phone</label>
                            <input class="form-control" id="phone" name="phone" value="{{ old('phone') }}">
                        </div>
                        <hr class="my-4">
                        <h3 class="h6" style="color: #50617a;">Provider details <span class="text-secondary fw-normal">(for doctors)</span></h3>
                        <div class="mb-3">
                            <label class="form-label" for="license_number">License number</label>
                            <input class="form-control" id="license_number" name="license_number" value="{{ old('license_number') }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="specialty">Specialty</label>
                            <input class="form-control" id="specialty" name="specialty" value="{{ old('specialty') }}">
                        </div>
                        <button class="btn btn-primary" type="submit">Create user</button>
                    </form>
                </div>
            </section>
        </div>
        <div class="col-12 col-xl-7">
            <section class="card">
                <div class="surface-header">
                    <div><h2>Your care community</h2><p>Accounts and their current access status.</p></div>
                    <span class="badge text-bg-primary">{{ $users->count() }} members</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Profile</th></tr></thead>
                        <tbody>
                        @foreach ($users as $account)
                            <tr>
                                <td>{{ $account->name }}</td>
                                <td>{{ $account->email }}</td>
                                <td><span class="badge text-bg-light">{{ $account->role->value }}</span></td>
                                <td>
                                    @if ($account->doctor)
                                        {{ $account->doctor->is_approved ? 'Approved doctor' : 'Pending approval' }}
                                    @elseif ($account->patient)
                                        Patient
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
@endsection
