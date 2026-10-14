<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DoctorAvailabilityController extends Controller
{
    public function slots(Request $request, Doctor $doctor): JsonResponse
    {
        abort_unless($doctor->is_approved && $doctor->is_accepting_patients, 404);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        return response()->json([
            'timezone' => config('app.timezone'),
            'slots' => DoctorAvailability::slotsFor(
                $doctor,
                Carbon::parse($data['date'])->startOfDay(),
            ),
        ]);
    }

    public function appointmentSlots(Request $request, Appointment $appointment): JsonResponse
    {
        $doctor = $request->user()->doctor;

        abort_unless(
            $doctor !== null
                && $appointment->doctor_id === $doctor->id
                && in_array($appointment->status, ['pending', 'approved', 'rescheduled'], true),
            404,
        );

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ]);

        return response()->json([
            'timezone' => config('app.timezone'),
            'slots' => DoctorAvailability::slotsFor(
                $doctor,
                Carbon::parse($data['date'])->startOfDay(),
                $appointment->id,
            ),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $doctor = $request->user()->doctor;

        abort_unless($doctor !== null, 403, 'Doctor profile is not configured.');

        $data = $request->validate([
            'availability' => ['required', 'array:0,1,2,3,4,5,6'],
            'availability.*' => ['required', 'array:start_time,end_time'],
            'availability.*.start_time' => ['nullable', 'date_format:H:i'],
            'availability.*.end_time' => ['nullable', 'date_format:H:i'],
        ]);
        $schedule = [];

        foreach ($data['availability'] as $day => $hours) {
            $startTime = $hours['start_time'] ?? null;
            $endTime = $hours['end_time'] ?? null;

            if (($startTime === null || $startTime === '') && ($endTime === null || $endTime === '')) {
                continue;
            }

            if ($startTime === null || $startTime === '' || $endTime === null || $endTime === '') {
                throw ValidationException::withMessages([
                    "availability.{$day}.end_time" => 'Enter both start and end times, or leave both blank for a day off.',
                ]);
            }

            if ($startTime >= $endTime) {
                throw ValidationException::withMessages([
                    "availability.{$day}.end_time" => 'The end time must be later than the start time.',
                ]);
            }

            if (! $this->isHalfHourBoundary($startTime) || ! $this->isHalfHourBoundary($endTime)) {
                throw ValidationException::withMessages([
                    "availability.{$day}.start_time" => 'Availability times must align to 30-minute increments.',
                ]);
            }

            $schedule[(int) $day] = [
                'start_time' => $startTime,
                'end_time' => $endTime,
            ];
        }

        DB::transaction(function () use ($doctor, $schedule): void {
            $lockedDoctor = Doctor::query()->whereKey($doctor->id)->lockForUpdate()->firstOrFail();
            $appointments = $lockedDoctor->appointments()
                ->whereIn('status', ['pending', 'approved', 'rescheduled'])
                ->where('scheduled_at', '>', now())
                ->get(['id', 'scheduled_at']);

            foreach ($appointments as $appointment) {
                $hours = $schedule[$appointment->scheduled_at->dayOfWeek] ?? null;
                $appointmentEnd = $appointment->scheduled_at->copy()->addMinutes(DoctorAvailability::SLOT_MINUTES);

                if (
                    $hours === null
                    || $appointment->scheduled_at->format('H:i:s') < $hours['start_time'].':00'
                    || $appointmentEnd->format('H:i:s') > $hours['end_time'].':00'
                ) {
                    throw ValidationException::withMessages([
                        'availability' => 'Your updated hours must include every existing future appointment.',
                    ]);
                }
            }

            $lockedDoctor->availabilities()->delete();
            $lockedDoctor->availabilities()->createMany(
                collect($schedule)
                    ->map(fn (array $hours, int $day): array => ['day_of_week' => $day, ...$hours])
                    ->values()
                    ->all(),
            );
        });

        return redirect()->route('profile.edit')->with('status', 'Weekly availability updated.');
    }

    private function isHalfHourBoundary(string $time): bool
    {
        return in_array(substr($time, -2), ['00', '30'], true);
    }
}
