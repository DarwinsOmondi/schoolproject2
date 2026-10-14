<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

#[Fillable(['doctor_id', 'day_of_week', 'start_time', 'end_time'])]
class DoctorAvailability extends Model
{
    public const SLOT_MINUTES = 30;

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * @return list<string>
     */
    public static function slotsFor(Doctor $doctor, Carbon $date, ?int $exceptAppointmentId = null): array
    {
        $availability = $doctor->availabilities()
            ->where('day_of_week', $date->dayOfWeek)
            ->first();

        if ($availability === null) {
            return [];
        }

        $windowStart = $date->copy()->setTimeFromTimeString($availability->start_time);
        $windowEnd = $date->copy()->setTimeFromTimeString($availability->end_time);
        $busyAppointments = Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', ['pending', 'approved', 'rescheduled'])
            ->where('scheduled_at', '<', $windowEnd->toDateTimeString())
            ->where('scheduled_at', '>', $windowStart->copy()->subMinutes(self::SLOT_MINUTES)->toDateTimeString())
            ->when($exceptAppointmentId !== null, fn ($query) => $query->where('id', '<>', $exceptAppointmentId))
            ->get(['id', 'scheduled_at']);
        $slots = [];

        for ($slot = $windowStart; $slot->copy()->addMinutes(self::SLOT_MINUTES)->lte($windowEnd); $slot->addMinutes(self::SLOT_MINUTES)) {
            if ($slot->lte(now())) {
                continue;
            }

            $slotEnd = $slot->copy()->addMinutes(self::SLOT_MINUTES);
            $isBooked = $busyAppointments->contains(
                fn (Appointment $appointment): bool => $appointment->scheduled_at->lt($slotEnd)
                    && $appointment->scheduled_at->copy()->addMinutes(self::SLOT_MINUTES)->gt($slot),
            );

            if (! $isBooked) {
                $slots[] = $slot->format('Y-m-d\TH:i');
            }
        }

        return $slots;
    }

    public static function isBookable(Doctor $doctor, Carbon $slot, ?int $exceptAppointmentId = null): bool
    {
        if ($slot->second !== 0 || ! in_array($slot->minute, [0, 30], true)) {
            return false;
        }

        $availability = $doctor->availabilities()
            ->where('day_of_week', $slot->dayOfWeek)
            ->first();

        if (
            $availability === null
            || $slot->format('H:i') < substr($availability->start_time, 0, 5)
            || $slot->copy()->addMinutes(self::SLOT_MINUTES)->format('H:i') > substr($availability->end_time, 0, 5)
        ) {
            return false;
        }

        $slotEnd = $slot->copy()->addMinutes(self::SLOT_MINUTES);

        return ! Appointment::query()
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', ['pending', 'approved', 'rescheduled'])
            ->where('scheduled_at', '<', $slotEnd->toDateTimeString())
            ->where('scheduled_at', '>', $slot->copy()->subMinutes(self::SLOT_MINUTES)->toDateTimeString())
            ->when($exceptAppointmentId !== null, fn ($query) => $query->where('id', '<>', $exceptAppointmentId))
            ->exists();
    }
}
