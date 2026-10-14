@extends('layouts.app')

@section('title', 'Profile')

@section('content')
    <header class="page-heading">
        <div class="page-heading-copy">
            <span class="page-eyebrow">YOUR ACCOUNT</span>
            <h1>Profile & preferences</h1>
            <p>Keep your account information and care preferences up to date.</p>
        </div>
        <span class="soft-pill">Schedule timezone · {{ config('app.timezone') }}</span>
    </header>

    <section class="card mb-4">
        <div class="card-header bg-white"><h2 class="h5 mb-0">Account and profile details</h2></div>
        <div class="card-body">
            <form method="POST" action="{{ route('profile.update') }}" class="row g-3">
                @csrf
                @method('PUT')
                <div class="col-12 col-md-6">
                    <label class="form-label" for="name">Name</label>
                    <input class="form-control" id="name" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255">
                </div>

                @if ($user->role->value === 'patient')
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="date_of_birth">Date of birth</label>
                        <input class="form-control" id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', $profile?->date_of_birth?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control" id="phone" name="phone" value="{{ old('phone', $profile?->phone) }}" maxlength="32">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="address">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="3" maxlength="2000">{{ old('address', $profile?->address) }}</textarea>
                    </div>
                @elseif ($user->role->value === 'doctor')
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="specialty">Specialty</label>
                        <input class="form-control" id="specialty" name="specialty" value="{{ old('specialty', $profile?->specialty) }}" required maxlength="255">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="bio">Professional bio</label>
                        <textarea class="form-control" id="bio" name="bio" rows="4" maxlength="5000">{{ old('bio', $profile?->bio) }}</textarea>
                    </div>
                    <div class="col-12 form-check ms-2">
                        <input type="hidden" name="is_accepting_patients" value="0">
                        <input class="form-check-input" type="checkbox" id="is_accepting_patients" name="is_accepting_patients" value="1" @checked(old('is_accepting_patients', $profile?->is_accepting_patients))>
                        <label class="form-check-label" for="is_accepting_patients">Accept new appointment requests</label>
                    </div>
                @endif

                <div class="col-12"><button class="btn btn-primary" type="submit">Save profile</button></div>
            </form>
        </div>
    </section>

    @if ($user->role->value === 'doctor')
        <section class="card">
            <div class="card-header bg-white">
                <h2 class="h5 mb-1">Weekly appointment hours</h2>
                <p class="small text-secondary mb-0">Patients can book 30-minute appointments within these hours. Clear both times for a day off.</p>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('doctor.availability.update') }}">
                    @csrf
                    @method('PUT')
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <thead><tr><th>Day</th><th>Start</th><th>End</th></tr></thead>
                            <tbody>
                            @foreach ($days as $day => $label)
                                <tr>
                                    <th scope="row">{{ $label }}</th>
                                    <td><input class="form-control" type="time" name="availability[{{ $day }}][start_time]" value="{{ old("availability.$day.start_time", substr($availability->get($day)?->start_time ?? '', 0, 5)) }}" step="1800" aria-label="{{ $label }} start time"></td>
                                    <td><input class="form-control" type="time" name="availability[{{ $day }}][end_time]" value="{{ old("availability.$day.end_time", substr($availability->get($day)?->end_time ?? '', 0, 5)) }}" step="1800" aria-label="{{ $label }} end time"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button class="btn btn-primary" type="submit">Save weekly hours</button>
                </form>
            </div>
        </section>
    @endif
@endsection
