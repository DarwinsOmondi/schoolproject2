<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Notifications\AppointmentActivityNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MessageController extends Controller
{
    public function index(Request $request, Appointment $appointment): View
    {
        $this->authorizeParticipant($request, $appointment);

        abort_unless(in_array($appointment->status, ['approved', 'rescheduled', 'completed'], true), 404);

        return view('messages.index', [
            'appointment' => $appointment->load(['patient.user:id,name', 'doctor.user:id,name']),
            'messages' => $appointment->messages()->with('sender:id,name')->oldest()->get(),
            'user' => $request->user(),
        ]);
    }

    public function store(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->authorizeParticipant($request, $appointment);

        abort_unless(in_array($appointment->status, ['approved', 'rescheduled', 'completed'], true), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $appointment->messages()->create([
            'sender_id' => $request->user()->id,
            'body' => $data['body'],
        ]);
        $recipient = $request->user()->role->value === 'patient'
            ? $appointment->doctor->user
            : $appointment->patient->user;
        $recipient->notify(new AppointmentActivityNotification(
            'You have a new appointment message.',
            route($recipient->role->value.'.appointments.messages.index', $appointment),
        ));

        return redirect()->route(
            $request->user()->role->value.'.appointments.messages.index',
            $appointment,
        );
    }

    private function authorizeParticipant(Request $request, Appointment $appointment): void
    {
        $user = $request->user();
        $isParticipant = match ($user->role->value) {
            'patient' => $user->patient?->appointments()->whereKey($appointment->id)->exists(),
            'doctor' => $user->doctor?->is_approved
                && $user->doctor?->appointments()->whereKey($appointment->id)->exists(),
            default => false,
        };

        abort_unless($isParticipant, 404);
    }
}
