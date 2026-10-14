@extends('layouts.app')

@section('title', 'Appointment messages')

@section('content')
    @php($role = $user->role->value)
    <header class="page-heading">
        <div>
            <a class="text-decoration-none" href="{{ route($role.'.appointments.index') }}">← Appointments</a>
            <div class="page-heading-copy mt-2">
                <span class="page-eyebrow">PRIVATE CARE CHAT</span>
                <h1>Consultation messages</h1>
            </div>
            <p class="text-secondary mb-0">
                Appointment #{{ $appointment->id }} ·
                {{ $role === 'patient' ? $appointment->doctor->user->name : $appointment->patient->user->name }}
            </p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route('appointments.video', $appointment) }}">Video consultation</a>
    </header>

    <section class="card">
        <div class="card-body">
            <div class="bg-body-tertiary rounded p-3 mb-3" style="min-height: 16rem; max-height: 55vh; overflow-y: auto;" aria-live="polite">
                @forelse ($messages as $message)
                    <article class="mb-3 {{ $message->sender_id === $user->id ? 'text-end' : '' }}">
                        <div class="d-inline-block text-start rounded px-3 py-2 {{ $message->sender_id === $user->id ? 'bg-primary text-white' : 'bg-white border' }}" style="max-width: 85%;">
                            <div class="small fw-semibold">{{ $message->sender->name }}</div>
                            <p class="mb-1" style="white-space: pre-wrap;">{{ $message->body }}</p>
                            <time class="small opacity-75">{{ $message->created_at->format('M j, g:i A') }}</time>
                        </div>
                    </article>
                @empty
                    <div class="empty-state"><span class="empty-state-icon" aria-hidden="true">✳</span><strong>Start a conversation</strong><p>Send a secure message to the other person in this appointment.</p></div>
                @endforelse
            </div>
            <form method="POST" action="{{ route($role.'.appointments.messages.store', $appointment) }}">
                @csrf
                <label class="form-label" for="body">Message</label>
                <textarea class="form-control mb-2" id="body" name="body" rows="3" maxlength="5000" required>{{ old('body') }}</textarea>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-secondary">Messages are private to this patient and doctor.</small>
                    <button class="btn btn-primary" type="submit">Send message</button>
                </div>
            </form>
        </div>
    </section>
@endsection
