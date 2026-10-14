<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorAvailability;
use App\Notifications\AppointmentActivityNotification;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse|ViewContract
    {
        $user = $request->user();
        abort_if($user->role->value === 'doctor' && ! $user->doctor?->is_approved, 403);
        $appointments = match ($user->role->value) {
            'patient' => $user->patient?->appointments(),
            'doctor' => $user->doctor?->appointments(),
            default => null,
        };

        abort_unless($appointments !== null, 403);

        $appointments = $appointments->with([
            'doctor:id,user_id,specialty',
            'doctor.user:id,name',
            'patient:id,user_id',
            'patient.user:id,name',
        ])
            ->orderByDesc('scheduled_at')
            ->paginate(20);

        return $request->expectsJson()
            ? response()->json($appointments)
            : view('appointments.index', ['appointments' => $appointments, 'role' => $user->role]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $patient = $request->user()->patient;

        abort_unless($patient !== null, 403, 'Patient profile is not configured.');

        $data = $request->validate([
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['scheduled_at'] = Carbon::parse($data['scheduled_at'])->toDateTimeString();

        $appointment = DB::transaction(function () use ($patient, $data): Appointment {
            $doctor = Doctor::query()
                ->where('is_approved', true)
                ->where('is_accepting_patients', true)
                ->lockForUpdate()
                ->findOrFail($data['doctor_id']);

            $this->assertSlotAvailable($doctor, $data['scheduled_at']);

            return $patient->appointments()->create([
                ...$data,
                'doctor_id' => $doctor->id,
                'status' => 'pending',
                'meeting_url' => 'https://'.config('services.jitsi.domain').'/'.Str::random(48),
            ]);
        });
        $appointment->doctor->user->notify(new AppointmentActivityNotification(
            'A new appointment request is waiting for your review.',
            route('doctor.appointments.index'),
        ));

        if ($request->expectsJson()) {
            return response()->json($appointment, 201);
        }

        return redirect()->route('patient.dashboard')->with('status', 'Appointment requested. The doctor must approve it.');
    }

    public function show(Request $request, Appointment $appointment): JsonResponse|ViewContract
    {
        $user = $request->user();
        $authorized = match ($user->role->value) {
            'patient' => $user->patient?->appointments()->whereKey($appointment->id)->exists(),
            'doctor' => $user->doctor?->is_approved
                && $user->doctor?->appointments()->whereKey($appointment->id)->exists(),
            default => false,
        };

        abort_unless($authorized, 404);

        $appointment->load([
            'doctor:id,user_id,specialty',
            'doctor.user:id,name',
            'patient:id,user_id',
            'patient.user:id,name',
        ]);

        return $request->expectsJson()
            ? response()->json($appointment)
            : view('appointments.show', ['appointment' => $appointment, 'role' => $user->role]);
    }

    public function video(Request $request, Appointment $appointment): View
    {
        $user = $request->user();
        $authorized = match ($user->role->value) {
            'patient' => $user->patient?->appointments()->whereKey($appointment->id)->exists(),
            'doctor' => $user->doctor?->is_approved
                && $user->doctor?->appointments()->whereKey($appointment->id)->exists(),
            default => false,
        };

        abort_unless($authorized, 404);
        abort_unless(in_array($appointment->status, ['approved', 'rescheduled'], true), 409);

        $jitsiDomain = config('services.jitsi.domain');
        $roomName = trim((string) parse_url($appointment->meeting_url, PHP_URL_PATH), '/');

        if (
            parse_url($appointment->meeting_url, PHP_URL_HOST) !== $jitsiDomain
            || $roomName === ''
        ) {
            $roomName = Str::random(48);
            $appointment->update([
                'meeting_url' => 'https://'.$jitsiDomain.'/'.$roomName,
            ]);
        }

        return view('appointments.video', [
            'appointment' => $appointment,
            'jitsiDomain' => $jitsiDomain,
            'roomName' => $roomName,
            'endCallUrl' => route($user->role->value.'.appointments.index'),
            'displayName' => $user->role->value === 'doctor' ? 'Doctor' : 'Patient',
        ]);
    }

    public function update(Request $request, Appointment $appointment): JsonResponse|RedirectResponse
    {
        $doctor = $request->user()->doctor;

        abort_unless($doctor !== null, 403, 'Doctor profile is not configured.');
        abort_unless($doctor->is_approved, 403, 'Doctor profile is awaiting administrator approval.');

        $appointment = $doctor->appointments()->findOrFail($appointment->id);
        abort_if(in_array($appointment->status, ['completed', 'cancelled'], true), 409);

        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rescheduled', 'completed'])],
            'scheduled_at' => [
                Rule::requiredIf(fn () => $request->input('status') === 'rescheduled'),
                'prohibited_unless:status,rescheduled',
                'nullable',
                'date',
                'after:now',
            ],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        if (isset($data['scheduled_at'])) {
            $data['scheduled_at'] = Carbon::parse($data['scheduled_at'])->toDateTimeString();
        }
        $appointment = DB::transaction(function () use ($doctor, $appointment, $data): Appointment {
            Doctor::query()->whereKey($doctor->id)->lockForUpdate()->firstOrFail();
            $appointment = $doctor->appointments()->lockForUpdate()->findOrFail($appointment->id);

            abort_if(in_array($appointment->status, ['completed', 'cancelled'], true), 409);

            if (isset($data['scheduled_at'])) {
                $this->assertSlotAvailable($doctor, $data['scheduled_at'], $appointment->id);
            }

            $appointment->update($data);

            return $appointment->refresh();
        });
        $patient = $appointment->patient->user;
        $patient->notify(new AppointmentActivityNotification(
            match ($appointment->status) {
                'approved' => 'Your appointment has been approved.',
                'rescheduled' => 'Your appointment has been rescheduled.',
                'completed' => 'Your consultation has been marked complete.',
            },
            route('patient.appointments.show', $appointment),
        ));

        if ($request->expectsJson()) {
            return response()->json($appointment);
        }

        return redirect()->route('doctor.dashboard')->with('status', 'Appointment updated.');
    }

    private function assertSlotAvailable(Doctor $doctor, string $scheduledAt, ?int $exceptAppointmentId = null): void
    {
        if (! DoctorAvailability::isBookable($doctor, Carbon::parse($scheduledAt), $exceptAppointmentId)) {
            throw ValidationException::withMessages([
                'scheduled_at' => 'This appointment time is no longer available or outside the doctor’s published hours.',
            ]);
        }
    }
}
