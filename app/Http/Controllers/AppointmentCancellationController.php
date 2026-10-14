<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Notifications\AppointmentActivityNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentCancellationController extends Controller
{
    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $user = $request->user();
        $isPatient = $user->role->value === 'patient'
            && $user->patient?->appointments()->whereKey($appointment->id)->exists();
        $isDoctor = $user->role->value === 'doctor'
            && $user->doctor?->is_approved
            && $user->doctor?->appointments()->whereKey($appointment->id)->exists();

        abort_unless($isPatient || $isDoctor, 404);
        $appointment = DB::transaction(function () use ($appointment): Appointment {
            $lockedAppointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);
            abort_unless(
                in_array($lockedAppointment->status, ['pending', 'approved', 'rescheduled'], true)
                    && $lockedAppointment->scheduled_at->isFuture(),
                409,
                'Only pending or upcoming appointments can be cancelled.',
            );

            $lockedAppointment->update(['status' => 'cancelled']);

            return $lockedAppointment;
        });

        $recipient = $isPatient
            ? $appointment->doctor->user
            : $appointment->patient->user;
        $recipient->notify(new AppointmentActivityNotification(
            'An appointment was cancelled by the other participant.',
            $isPatient
                ? route('doctor.appointments.index')
                : route('patient.appointments.show', $appointment),
        ));

        return redirect()->route($user->role->value.'.appointments.index')
            ->with('status', 'Appointment cancelled.');
    }
}
